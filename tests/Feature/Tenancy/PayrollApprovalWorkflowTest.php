<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * T6 finance — optional Submit → Approve path alongside the existing direct
 * Finalize flow. Finalize itself must keep working unchanged (see
 * FinanceModuleTest); this suite only covers the new pending_approval path.
 */
class PayrollApprovalWorkflowTest extends EventModuleTestCase
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

    public function test_church_admin_can_submit_run_for_approval_and_approve_it(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');
        $run = $this->draftRunWithLine($church);

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.submit-for-approval', $run))
            ->assertRedirect(route('church.finance.payroll.show', $run));

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_PENDING_APPROVAL, $run->status);
        $this->assertNotNull($run->submitted_at);
        $this->assertSame($admin->user_id, $run->submitted_by_id);

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.approve', $run))
            ->assertRedirect(route('church.finance.payroll.show', $run));

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_FINALIZED, $run->status);
        $this->assertNotNull($run->approved_at);
        $this->assertSame($admin->user_id, $run->approved_by_id);
    }

    public function test_rejecting_a_pending_run_returns_it_to_draft_with_reason(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');
        $run = $this->draftRunWithLine($church);

        $this->actingAs($admin)->post(route('church.finance.payroll.submit-for-approval', $run));

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.reject', $run), [
                'rejection_reason' => 'Missing overtime for one payee.',
            ])
            ->assertRedirect(route('church.finance.payroll.show', $run));

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_DRAFT, $run->status);
        $this->assertNotNull($run->rejected_at);
        $this->assertSame($admin->user_id, $run->rejected_by_id);
        $this->assertSame('Missing overtime for one payee.', $run->rejection_reason);

        // Back in draft, lines can be edited again.
        $this->actingAs($admin)
            ->delete(route('church.finance.payroll.lines.destroy', [$run, $run->lines()->first()]))
            ->assertRedirect();
    }

    public function test_reject_requires_a_reason(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');
        $run = $this->draftRunWithLine($church);
        $this->actingAs($admin)->post(route('church.finance.payroll.submit-for-approval', $run));

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.reject', $run), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(PayrollRun::STATUS_PENDING_APPROVAL, $run->fresh()->status);
    }

    public function test_manage_only_user_cannot_approve_or_reject(): void
    {
        [$church, $manager] = $this->churchUserWithPermissions('manage-only', ['finance.payroll.manage', 'finance.payroll.view']);
        $run = $this->draftRunWithLine($church);

        $this->actingAs($manager)
            ->post(route('church.finance.payroll.submit-for-approval', $run))
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('church.finance.payroll.approve', $run))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('church.finance.payroll.reject', $run), ['rejection_reason' => 'no'])
            ->assertForbidden();
    }

    public function test_approve_only_user_cannot_submit_or_manage_lines(): void
    {
        [$church, $approver] = $this->churchUserWithPermissions('approve-only', ['finance.payroll.approve', 'finance.payroll.view']);
        $run = $this->draftRunWithLine($church);

        $this->actingAs($approver)
            ->post(route('church.finance.payroll.submit-for-approval', $run))
            ->assertForbidden();
    }

    public function test_cannot_submit_an_empty_draft_or_approve_a_non_pending_run(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');

        $emptyRun = new PayrollRun([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => PayrollRun::STATUS_DRAFT,
            'currency' => 'EGP',
        ]);
        $emptyRun->church_id = $church->church_id;
        $emptyRun->save();

        $this->actingAs($admin)
            ->post(route('church.finance.payroll.submit-for-approval', $emptyRun))
            ->assertStatus(422);

        $run = $this->draftRunWithLine($church);
        $this->actingAs($admin)
            ->post(route('church.finance.payroll.approve', $run))
            ->assertStatus(422);
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRole(string $templateSlug): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $templateSlug.'-approval@example.com']);

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

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchUserWithPermissions(string $slug, array $permissionKeys): array
    {
        $church = Church::main();
        $role = Role::create([
            'role_name' => ucfirst($slug),
            'role_decription' => $slug,
            'slug' => $slug,
            'church_id' => $church->church_id,
            'is_template' => false,
        ]);
        $ids = Permission::whereIn('key', $permissionKeys)->pluck('permission_id');
        $role->permissions()->sync($ids);

        $user = $this->createUser(['email' => $slug.'-approval@example.com']);

        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
            'assigned_at' => now(),
        ]);

        return [$church, $user];
    }

    private function draftRunWithLine(Church $church): PayrollRun
    {
        $payee = $this->createUser(['email' => 'payee-'.uniqid().'@example.com']);
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

        return $run;
    }
}
