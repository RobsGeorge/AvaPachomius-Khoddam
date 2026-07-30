<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\UserChurchRole;
use App\Services\Finance\PayrollCadenceService;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * T6 residual — payroll cadence automation (generate next period, copying
 * forward the previous run's payees as a draft for review).
 */
class PayrollCadenceAutomationTest extends EventModuleTestCase
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

    public function test_generates_next_period_and_copies_lines(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');
        $payee = $this->createUser(['email' => 'cadence-payee@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $payee->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $previous = new PayrollRun([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => PayrollRun::STATUS_FINALIZED,
            'currency' => 'EGP',
        ]);
        $previous->church_id = $church->church_id;
        $previous->save();

        $line = new PayrollLine([
            'payroll_run_id' => $previous->payroll_run_id,
            'user_id' => $payee->user_id,
            'gross_minor' => 25000,
            'deductions_minor' => 1000,
            'net_minor' => 24000,
            'currency' => 'EGP',
            'fx_rate' => '1',
            'notes' => 'Base salary',
        ]);
        $line->church_id = $church->church_id;
        $line->save();

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.generate-next'))
            ->assertRedirect();

        $next = PayrollRun::query()->where('payroll_run_id', '!=', $previous->payroll_run_id)->firstOrFail();
        $this->assertSame('2026-08-01', $next->period_start->format('Y-m-d'));
        $this->assertSame('2026-08-31', $next->period_end->format('Y-m-d'));
        $this->assertSame(PayrollRun::STATUS_DRAFT, $next->status);
        $this->assertSame('EGP', $next->currency);
        $this->assertSame($church->church_id, $next->church_id);

        $copiedLine = PayrollLine::query()->where('payroll_run_id', $next->payroll_run_id)->firstOrFail();
        $this->assertSame($payee->user_id, $copiedLine->user_id);
        $this->assertSame(25000, $copiedLine->gross_minor);
        $this->assertSame(1000, $copiedLine->deductions_minor);
        $this->assertSame(24000, $copiedLine->net_minor);
        $this->assertSame('Base salary', $copiedLine->notes);
    }

    public function test_skips_when_no_previous_run_exists(): void
    {
        $church = Church::main();
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        TenantContext::set($church);

        $result = app(PayrollCadenceService::class)->generateNextPeriod($church);

        $this->assertSame(PayrollCadenceService::STATUS_NO_PREVIOUS_RUN, $result['status']);
        $this->assertSame(0, PayrollRun::query()->count());
    }

    public function test_skips_when_next_period_already_has_a_run(): void
    {
        $church = Church::main();
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        TenantContext::set($church);

        $previous = new PayrollRun([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => PayrollRun::STATUS_FINALIZED,
            'currency' => 'EGP',
        ]);
        $previous->church_id = $church->church_id;
        $previous->save();

        $existingNext = new PayrollRun([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollRun::STATUS_DRAFT,
            'currency' => 'EGP',
        ]);
        $existingNext->church_id = $church->church_id;
        $existingNext->save();

        $result = app(PayrollCadenceService::class)->generateNextPeriod($church);

        $this->assertSame(PayrollCadenceService::STATUS_ALREADY_EXISTS, $result['status']);
        $this->assertSame(2, PayrollRun::query()->count());
    }

    public function test_running_generator_twice_is_idempotent(): void
    {
        $church = Church::main();
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        TenantContext::set($church);

        $previous = new PayrollRun([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => PayrollRun::STATUS_FINALIZED,
            'currency' => 'EGP',
        ]);
        $previous->church_id = $church->church_id;
        $previous->save();

        $service = app(PayrollCadenceService::class);
        $first = $service->generateNextPeriod($church);
        $second = $service->generateNextPeriod($church);

        $this->assertSame(PayrollCadenceService::STATUS_GENERATED, $first['status']);
        $this->assertSame(PayrollCadenceService::STATUS_ALREADY_EXISTS, $second['status']);
        $this->assertSame(2, PayrollRun::query()->count());
    }

    public function test_console_command_iterates_multiple_churches_without_cross_church_copying(): void
    {
        $churchA = Church::main();
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($churchA);
        $churchB = Church::create(['slug' => 'cadence-iso-'.uniqid('', true), 'name' => 'Cadence Isolation Church', 'status' => 'active']);
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($churchB);

        TenantContext::set($churchA);
        $runA = new PayrollRun([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => PayrollRun::STATUS_FINALIZED,
            'currency' => 'EGP',
        ]);
        $runA->church_id = $churchA->church_id;
        $runA->save();

        // Church B has no prior run — should be skipped, not seeded from Church A.
        TenantContext::clear();

        Artisan::call('payroll:generate-next-period');

        TenantContext::set($churchA);
        $this->assertSame(2, PayrollRun::query()->count());

        TenantContext::set($churchB);
        $this->assertSame(0, PayrollRun::query()->count());
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRole(string $templateSlug): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $templateSlug.'-cadence-'.uniqid('', true).'@example.com']);

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
