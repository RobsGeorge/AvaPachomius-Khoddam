<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\ConfessionBooking;
use App\Models\ConfessionSlot;
use App\Models\Priest;
use App\Models\PriestSecretary;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * PAC2 — confession week grid, generate, cancel/reschedule, book-on-behalf, secretaries.
 */
class ConfessionCalendarPac2Test extends EventModuleTestCase
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
        app(RoleTemplateService::class)->ensureChurchTemplates();
    }

    public function test_week_grid_index_and_generate_weekly_slots(): void
    {
        [$church, $priestUser, $roles] = $this->seedPriestChurch();

        TenantContext::set($church);
        $priest = Priest::create([
            'user_id' => $priestUser->user_id,
            'title' => 'Abouna',
            'status' => Priest::STATUS_ACTIVE,
        ]);

        $this->actingAs($priestUser)
            ->get(route('church.confession.index'))
            ->assertOk()
            ->assertSee(__('church_mgmt.confession_title'), false);

        $this->actingAs($priestUser)
            ->post(route('church.confession.generate.store'), [
                'priest_id' => $priest->priest_id,
                'weekdays' => [6],
                'time_start' => '09:00',
                'time_end' => '10:00',
                'weeks' => 2,
                'capacity' => 1,
                'location' => 'Chapel',
            ])
            ->assertRedirect(route('church.confession.index'));

        $this->assertGreaterThanOrEqual(1, ConfessionSlot::query()->where('priest_id', $priest->priest_id)->where('recurrence', 'weekly')->count());
    }

    public function test_open_block_and_book_on_behalf_by_secretary(): void
    {
        [$church, $priestUser, $roles] = $this->seedPriestChurch();
        $secretary = $this->createUser(['email' => 'pac2-sec@example.com']);
        $member = $this->createUser(['email' => 'pac2-member@example.com']);

        foreach ([$secretary, $member] as $u) {
            ChurchUser::create([
                'church_id' => $church->church_id,
                'user_id' => $u->user_id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $secretary->user_id,
            'role_id' => $roles['secretary']->role_id,
            'assigned_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $member->user_id,
            'role_id' => $roles['servant']->role_id,
            'assigned_at' => now(),
        ]);

        TenantContext::set($church);
        $priest = Priest::create([
            'user_id' => $priestUser->user_id,
            'status' => Priest::STATUS_ACTIVE,
        ]);
        PriestSecretary::create([
            'priest_id' => $priest->priest_id,
            'user_id' => $secretary->user_id,
            'status' => PriestSecretary::STATUS_ACTIVE,
        ]);

        $slot = ConfessionSlot::create([
            'priest_id' => $priest->priest_id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'capacity' => 1,
            'status' => ConfessionSlot::STATUS_OPEN,
        ]);

        $this->actingAs($secretary)
            ->post(route('church.confession.status', $slot), ['status' => 'closed'])
            ->assertRedirect();

        $this->assertSame(ConfessionSlot::STATUS_CLOSED, $slot->fresh()->status);

        $this->actingAs($secretary)
            ->post(route('church.confession.status', $slot), ['status' => 'open'])
            ->assertRedirect();

        $this->actingAs($secretary)
            ->post(route('church.confession.book-on-behalf.store', $slot), [
                'user_id' => $member->user_id,
                'notes' => 'For member',
            ])
            ->assertRedirect(route('church.confession.index'));

        $booking = ConfessionBooking::query()->where('user_id', $member->user_id)->first();
        $this->assertNotNull($booking);
        $this->assertSame((int) $secretary->user_id, (int) $booking->booked_by_user_id);
        $this->assertSame('For member', $booking->notes);
    }

    public function test_member_can_cancel_and_reschedule(): void
    {
        [$church, $priestUser, $roles] = $this->seedPriestChurch();
        $member = $this->createUser(['email' => 'pac2-booker@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $member->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $member->user_id,
            'role_id' => $roles['servant']->role_id,
            'assigned_at' => now(),
        ]);

        TenantContext::set($church);
        $priest = Priest::create([
            'user_id' => $priestUser->user_id,
            'status' => Priest::STATUS_ACTIVE,
        ]);
        $slotA = ConfessionSlot::create([
            'priest_id' => $priest->priest_id,
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHour(),
            'capacity' => 1,
            'status' => ConfessionSlot::STATUS_OPEN,
        ]);
        $slotB = ConfessionSlot::create([
            'priest_id' => $priest->priest_id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHour(),
            'capacity' => 1,
            'status' => ConfessionSlot::STATUS_OPEN,
        ]);

        $this->actingAs($member)
            ->post(route('church.confession.book', $slotA))
            ->assertRedirect();

        $booking = ConfessionBooking::query()->where('user_id', $member->user_id)->where('status', 'confirmed')->firstOrFail();

        $this->actingAs($member)
            ->post(route('church.confession.bookings.reschedule.store', $booking), [
                'confession_slot_id' => $slotB->confession_slot_id,
            ])
            ->assertRedirect(route('church.confession.my-bookings'));

        $this->assertSame(ConfessionBooking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertDatabaseHas('confession_booking', [
            'user_id' => $member->user_id,
            'confession_slot_id' => $slotB->confession_slot_id,
            'status' => ConfessionBooking::STATUS_CONFIRMED,
        ]);
    }

    public function test_priest_can_assign_secretary(): void
    {
        [$church, $priestUser] = $this->seedPriestChurch();
        $secretary = $this->createUser(['email' => 'pac2-sec2@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $secretary->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        TenantContext::set($church);
        $priest = Priest::create([
            'user_id' => $priestUser->user_id,
            'status' => Priest::STATUS_ACTIVE,
        ]);

        $this->actingAs($priestUser)
            ->post(route('church.priests.secretaries.store', $priest), [
                'user_id' => $secretary->user_id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('priest_secretary', [
            'priest_id' => $priest->priest_id,
            'user_id' => $secretary->user_id,
            'status' => PriestSecretary::STATUS_ACTIVE,
        ]);
    }

    /** @return array{0: Church, 1: \App\Models\User, 2: array<string, \App\Models\Role>} */
    private function seedPriestChurch(): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $priestUser = $this->createUser(['email' => 'pac2-priest-'.uniqid('', true).'@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $priestUser->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $priestUser->user_id,
            'role_id' => $roles['priest']->role_id,
            'assigned_at' => now(),
        ]);

        return [$church, $priestUser, $roles];
    }
}
