<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

/**
 * T4 — church-admin members self-service + invite-by-email when user is unknown.
 */
class ChurchMembersInviteTest extends EventModuleTestCase
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

    public function test_church_admin_can_view_members_page(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->get(route('church.members.index'))
            ->assertOk()
            ->assertSee(__('tenancy.add_or_invite_member'), false);
    }

    public function test_servant_cannot_manage_members(): void
    {
        [, $servant] = $this->churchWithRole('servant');

        $this->actingAs($servant)
            ->get(route('church.members.index'))
            ->assertForbidden();
    }

    public function test_church_admin_can_add_existing_user_by_email(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');
        $existing = $this->createUser(['email' => 'existing-member@example.com']);

        $this->actingAs($admin)
            ->post(route('church.members.store'), [
                'email' => 'existing-member@example.com',
            ])
            ->assertRedirect(route('church.members.index'));

        $this->assertDatabaseHas('church_user', [
            'church_id' => $church->church_id,
            'user_id' => $existing->user_id,
            'status' => 'active',
        ]);
    }

    public function test_church_admin_can_invite_unknown_email(): void
    {
        Mail::fake();
        [$church, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->post(route('church.members.store'), [
                'email' => 'brand-new@example.com',
                'first_name' => 'New',
                'second_name' => 'Invitee',
                'send_email' => 1,
            ])
            ->assertRedirect(route('church.members.index'));

        $user = User::where('email', 'brand-new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('church_user', [
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'invited',
        ]);
        $this->assertDatabaseHas('invitations', [
            'church_id' => $church->church_id,
            'email' => 'brand-new@example.com',
            'status' => Invitation::STATUS_PENDING,
        ]);
    }

    public function test_invite_without_first_name_is_rejected(): void
    {
        Mail::fake();
        [, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->from(route('church.members.index'))
            ->post(route('church.members.store'), [
                'email' => 'needs-name@example.com',
                'send_email' => 1,
            ])
            ->assertRedirect(route('church.members.index'))
            ->assertSessionHasErrors('first_name');

        $this->assertNull(User::where('email', 'needs-name@example.com')->first());
    }

    public function test_superadmin_add_member_invites_unknown_email(): void
    {
        Mail::fake();
        $super = $this->createUser([
            'email' => 'sa-invite@example.com',
            'is_superadmin' => true,
        ]);
        $church = Church::main();
        app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);

        $this->actingAs($super)
            ->post(route('superadmin.churches.members.store', $church), [
                'email' => 'sa-new@example.com',
                'first_name' => 'Platform',
                'send_email' => 1,
            ])
            ->assertRedirect();

        $user = User::where('email', 'sa-new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(
            ChurchUser::where('church_id', $church->church_id)
                ->where('user_id', $user->user_id)
                ->where('status', 'invited')
                ->exists()
        );
    }

    private function churchWithRole(string $templateSlug): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $templateSlug.'-members@example.com']);

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
