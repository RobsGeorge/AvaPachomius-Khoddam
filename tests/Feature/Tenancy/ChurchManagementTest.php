<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\ConfessionBooking;
use App\Models\ConfessionSlot;
use App\Models\HomeVisit;
use App\Models\Priest;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * T5 — priests, confession calendars, home visits (CRUD, ownership, capacity, isolation).
 */
class ChurchManagementTest extends EventModuleTestCase
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

    public function test_church_admin_can_register_a_priest(): void
    {
        [$church, $admin] = $this->churchWithRole('church-admin');
        $priestUser = $this->createUser(['email' => 'abouna@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $priestUser->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('church.priests.store'), [
                'email' => 'abouna@example.com',
                'title' => 'Abouna',
                'status' => Priest::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('church.priests.index'));

        $this->assertDatabaseHas('priest', [
            'user_id' => $priestUser->user_id,
            'church_id' => $church->church_id,
            'status' => Priest::STATUS_ACTIVE,
        ]);
    }

    public function test_servant_cannot_manage_priests(): void
    {
        [, $servant] = $this->churchWithRole('servant');

        $this->actingAs($servant)
            ->get(route('church.priests.create'))
            ->assertForbidden();
    }

    public function test_priest_creates_slot_and_served_can_book_until_full(): void
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);

        $priestUser = $this->createUser(['email' => 'slot-priest@example.com']);
        $served = $this->createUser(['email' => 'served@example.com']);
        $other = $this->createUser(['email' => 'other-served@example.com']);

        foreach ([$priestUser, $served, $other] as $member) {
            ChurchUser::create([
                'church_id' => $church->church_id,
                'user_id' => $member->user_id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $priestUser->user_id,
            'role_id' => $roles['priest']->role_id,
            'assigned_at' => now(),
        ]);
        foreach ([$served, $other] as $member) {
            UserChurchRole::create([
                'church_id' => $church->church_id,
                'user_id' => $member->user_id,
                'role_id' => $roles['servant']->role_id,
                'assigned_at' => now(),
            ]);
        }

        $priest = new Priest([
            'user_id' => $priestUser->user_id,
            'title' => 'Abouna',
            'status' => Priest::STATUS_ACTIVE,
        ]);
        $priest->church_id = $church->church_id;
        $priest->save();

        $this->actingAs($priestUser)
            ->post(route('church.confession.store'), [
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
                'capacity' => 1,
                'location' => 'Chapel',
            ])
            ->assertRedirect(route('church.confession.index'));

        $slot = ConfessionSlot::query()->firstOrFail();

        $this->actingAs($served)
            ->post(route('church.confession.book', $slot))
            ->assertRedirect(route('church.confession.index'));

        $this->assertDatabaseHas('confession_booking', [
            'confession_slot_id' => $slot->confession_slot_id,
            'user_id' => $served->user_id,
            'status' => ConfessionBooking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($other)
            ->post(route('church.confession.book', $slot))
            ->assertStatus(422);
    }

    public function test_home_visit_assignee_sees_own_rows_admin_sees_all(): void
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);

        $admin = $this->createUser(['email' => 'hv-admin@example.com']);
        $servant = $this->createUser(['email' => 'hv-servant@example.com']);
        $other = $this->createUser(['email' => 'hv-other@example.com']);

        foreach ([$admin, $servant, $other] as $member) {
            ChurchUser::create([
                'church_id' => $church->church_id,
                'user_id' => $member->user_id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $admin->user_id,
            'role_id' => $roles['church-admin']->role_id,
            'assigned_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $servant->user_id,
            'role_id' => $roles['servant']->role_id,
            'assigned_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $other->user_id,
            'role_id' => $roles['servant']->role_id,
            'assigned_at' => now(),
        ]);

        foreach ([[$servant, 'Family A'], [$other, 'Family B']] as [$assignee, $subject]) {
            $visit = new HomeVisit([
                'assigned_user_id' => $assignee->user_id,
                'subject_name' => $subject,
                'scheduled_at' => now()->addDays(2),
                'status' => HomeVisit::STATUS_SCHEDULED,
            ]);
            $visit->church_id = $church->church_id;
            $visit->save();
        }

        $this->actingAs($servant)
            ->get(route('church.home-visits.index'))
            ->assertOk()
            ->assertSee('Family A')
            ->assertDontSee('Family B');

        $this->actingAs($admin)
            ->get(route('church.home-visits.index'))
            ->assertOk()
            ->assertSee('Family A')
            ->assertSee('Family B');
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRole(string $templateSlug): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $templateSlug.'-t5@example.com']);

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
