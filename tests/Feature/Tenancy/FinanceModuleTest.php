<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\MoneyIn;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * T6 — payroll + money-in first cut (integer minor units, no float arithmetic).
 */
class FinanceModuleTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    public function test_money_helper_preserves_minor_units_without_floats(): void
    {
        $this->assertSame(10050, Money::toMinor('100.50'));
        $this->assertSame('100.50', Money::fromMinor(10050));
        $this->assertSame(7550, Money::net(10000, 2450));
        $this->assertSame(Money::DEFAULT_CURRENCY, 'EGP');
        $this->assertSame(Money::DEFAULT_FX_RATE, '1');
    }

    public function test_church_admin_can_create_payroll_run_and_line(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');
        $payee = $this->createUser(['email' => 'payee@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $payee->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.store'), [
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'currency' => 'EGP',
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->assertSame(PayrollRun::STATUS_DRAFT, $run->status);
        $this->assertSame($church->church_id, $run->church_id);

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.lines.store', $run), [
                'email' => 'payee@example.com',
                'gross' => '250.75',
                'deductions' => '10.25',
            ])
            ->assertRedirect();

        $line = PayrollLine::query()->firstOrFail();
        $this->assertSame(25075, $line->gross_minor);
        $this->assertSame(1025, $line->deductions_minor);
        $this->assertSame(24050, $line->net_minor);
        $this->assertSame('EGP', $line->currency);
        $this->assertSame('1', $line->fx_rate);
        $this->assertIsString($line->fx_rate);
    }

    public function test_finalized_payroll_run_rejects_new_lines(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');
        $payee = $this->createUser(['email' => 'locked-payee@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $payee->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $run = new PayrollRun([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => PayrollRun::STATUS_DRAFT,
            'currency' => 'EGP',
        ]);
        $run->church_id = $church->church_id;
        $run->save();

        $line = new PayrollLine([
            'payroll_run_id' => $run->payroll_run_id,
            'user_id' => $payee->user_id,
            'gross_minor' => 10000,
            'deductions_minor' => 0,
            'net_minor' => 10000,
            'currency' => 'EGP',
            'fx_rate' => '1',
        ]);
        $line->church_id = $church->church_id;
        $line->save();

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.finalize', $run))
            ->assertRedirect(route('church.finance.payroll.show', $run));

        $this->assertSame(PayrollRun::STATUS_FINALIZED, $run->fresh()->status);

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.lines.store', $run), [
                'email' => 'locked-payee@example.com',
                'gross' => '1.00',
            ])
            ->assertStatus(422);
    }

    public function test_servant_cannot_manage_finance(): void
    {
        [, $servant] = $this->churchWithRole('servant');

        $this->actingAs($servant)
            ->get(route('church.finance.payroll.index'))
            ->assertForbidden();

        $this->actingAs($servant)
            ->get(route('church.finance.money-in.index'))
            ->assertForbidden();
    }

    public function test_money_in_stores_integer_minor_units_and_can_be_deleted(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->post(route('church.finance.money-in.store'), [
                'source' => 'Sunday offering',
                'category' => 'offering',
                'amount' => '123.45',
                'currency' => 'EGP',
                'fx_rate' => '1',
                'received_at' => '2026-07-15',
            ])
            ->assertRedirect(route('church.finance.money-in.index'));

        $entry = MoneyIn::query()->firstOrFail();
        $this->assertSame(12345, $entry->amount_minor);
        $this->assertSame($church->church_id, $entry->church_id);
        $this->assertSame('1', $entry->fx_rate);

        $this->actingAs($admin)
            ->delete(route('church.finance.money-in.destroy', $entry))
            ->assertRedirect(route('church.finance.money-in.index'));

        $this->assertDatabaseMissing('money_in', ['money_in_id' => $entry->money_in_id]);
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRole(string $templateSlug): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $templateSlug.'-t6@example.com']);

        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $roles[$templateSlug]->role_id,
            'assigned_at' => now(),
        ]);

        return [$church, $user];
    }
}
