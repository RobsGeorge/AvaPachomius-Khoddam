<?php

namespace Tests\Feature\Tenancy;

use App\Models\AppointmentBooking;
use App\Models\AppointmentSlot;
use App\Models\AppointmentType;
use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\ConfessionBooking;
use App\Models\ConfessionSlot;
use App\Models\Priest;
use App\Models\PriestSecretary;
use App\Models\UserChurchRole;
use App\Models\UserNotification;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

/**
 * PAC3 pastoral calendar + PAC4 lifecycle notifications / reminders.
 */
class AppointmentCalendarPac34Test extends EventModuleTestCase
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
        Mail::fake();
    }

    public function test_priest_can_create_type_generate_slots_and_member_books(): void
    {
        [$church, $priestUser, $roles] = $this->seedPriestChurch();
        $member = $this->createUser(['email' => 'pac3-member@example.com']);
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

        $this->actingAs($priestUser)
            ->post(route('church.appointments.types.store'), [
                'name_ar' => 'مشورة',
                'name_en' => 'Counseling',
                'default_capacity' => 2,
                'default_duration_minutes' => 45,
                'status' => 'active',
            ])
            ->assertRedirect(route('church.appointments.types.index'));

        $type = AppointmentType::query()->where('slug', 'counseling')->first()
            ?? AppointmentType::query()->first();
        $this->assertNotNull($type);
        $this->assertSame(2, (int) $type->default_capacity);

        $this->actingAs($priestUser)
            ->get(route('church.appointments.index'))
            ->assertOk()
            ->assertSee(__('church_mgmt.appointment_title'), false);

        $this->actingAs($priestUser)
            ->post(route('church.appointments.generate.store'), [
                'priest_id' => $priest->priest_id,
                'appointment_type_id' => $type->appointment_type_id,
                'weekdays' => [6],
                'time_start' => '11:00',
                'time_end' => '12:00',
                'weeks' => 2,
                'capacity' => 2,
                'location' => 'Office',
            ])
            ->assertRedirect(route('church.appointments.index'));

        $slot = AppointmentSlot::query()->where('priest_id', $priest->priest_id)->first();
        $this->assertNotNull($slot);
        $this->assertSame((int) $type->appointment_type_id, (int) $slot->appointment_type_id);

        $this->actingAs($member)
            ->post(route('church.appointments.book', $slot))
            ->assertRedirect(route('church.appointments.index'));

        $booking = AppointmentBooking::query()->where('user_id', $member->user_id)->first();
        $this->assertNotNull($booking);
        $this->assertSame(AppointmentBooking::STATUS_CONFIRMED, $booking->status);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $member->user_id,
            'type' => 'appointment_booking_confirmed',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $priestUser->user_id,
            'type' => 'appointment_booking_confirmed',
        ]);
    }

    public function test_secretary_book_on_behalf_and_member_reschedule_same_type(): void
    {
        [$church, $priestUser, $roles] = $this->seedPriestChurch();
        $secretary = $this->createUser(['email' => 'pac3-sec@example.com']);
        $member = $this->createUser(['email' => 'pac3-booker@example.com']);

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

        $type = AppointmentType::create([
            'slug' => 'meeting',
            'name_ar' => 'لقاء',
            'name_en' => 'Meeting',
            'default_capacity' => 1,
            'default_duration_minutes' => 60,
            'status' => AppointmentType::STATUS_ACTIVE,
        ]);
        $otherType = AppointmentType::create([
            'slug' => 'other',
            'name_ar' => 'آخر',
            'name_en' => 'Other',
            'default_capacity' => 1,
            'default_duration_minutes' => 30,
            'status' => AppointmentType::STATUS_ACTIVE,
        ]);

        $slotA = AppointmentSlot::create([
            'priest_id' => $priest->priest_id,
            'appointment_type_id' => $type->appointment_type_id,
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHour(),
            'capacity' => 1,
            'status' => AppointmentSlot::STATUS_OPEN,
        ]);
        $slotB = AppointmentSlot::create([
            'priest_id' => $priest->priest_id,
            'appointment_type_id' => $type->appointment_type_id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHour(),
            'capacity' => 1,
            'status' => AppointmentSlot::STATUS_OPEN,
        ]);
        $wrongTypeSlot = AppointmentSlot::create([
            'priest_id' => $priest->priest_id,
            'appointment_type_id' => $otherType->appointment_type_id,
            'starts_at' => now()->addDays(6),
            'ends_at' => now()->addDays(6)->addHour(),
            'capacity' => 1,
            'status' => AppointmentSlot::STATUS_OPEN,
        ]);

        $this->actingAs($secretary)
            ->post(route('church.appointments.book-on-behalf.store', $slotA), [
                'user_id' => $member->user_id,
                'notes' => 'Secretary note',
            ])
            ->assertRedirect(route('church.appointments.index'));

        $booking = AppointmentBooking::query()->where('user_id', $member->user_id)->firstOrFail();
        $this->assertSame((int) $secretary->user_id, (int) $booking->booked_by_user_id);
        $this->assertSame('Secretary note', $booking->notes);

        $this->actingAs($member)
            ->post(route('church.appointments.bookings.reschedule.store', $booking), [
                'appointment_slot_id' => $wrongTypeSlot->appointment_slot_id,
            ])
            ->assertSessionHasErrors('slot');

        $this->actingAs($member)
            ->post(route('church.appointments.bookings.reschedule.store', $booking), [
                'appointment_slot_id' => $slotB->appointment_slot_id,
            ])
            ->assertRedirect(route('church.appointments.my-bookings'));

        $this->assertSame(AppointmentBooking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertDatabaseHas('appointment_booking', [
            'user_id' => $member->user_id,
            'appointment_slot_id' => $slotB->appointment_slot_id,
            'status' => AppointmentBooking::STATUS_CONFIRMED,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $member->user_id,
            'type' => 'appointment_booking_rescheduled',
        ]);
    }

    public function test_confession_booking_also_emits_lifecycle_notification(): void
    {
        [$church, $priestUser, $roles] = $this->seedPriestChurch();
        $member = $this->createUser(['email' => 'pac4-conf@example.com']);
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
        $slot = ConfessionSlot::create([
            'priest_id' => $priest->priest_id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'capacity' => 1,
            'status' => ConfessionSlot::STATUS_OPEN,
        ]);

        $this->actingAs($member)
            ->post(route('church.confession.book', $slot))
            ->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $member->user_id,
            'type' => 'appointment_booking_confirmed',
        ]);

        $booking = ConfessionBooking::query()->where('user_id', $member->user_id)->firstOrFail();
        $this->actingAs($member)
            ->post(route('church.confession.bookings.cancel', $booking))
            ->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $member->user_id,
            'type' => 'appointment_booking_cancelled',
        ]);
    }

    public function test_reminder_command_fires_within_lead_window(): void
    {
        [$church, $priestUser, $roles] = $this->seedPriestChurch();
        $member = $this->createUser(['email' => 'pac4-remind@example.com']);
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
        $type = AppointmentType::create([
            'slug' => 'remind-type',
            'name_ar' => 'تذكير',
            'name_en' => 'Remind',
            'default_capacity' => 1,
            'default_duration_minutes' => 30,
            'status' => AppointmentType::STATUS_ACTIVE,
        ]);
        $slot = AppointmentSlot::create([
            'priest_id' => $priest->priest_id,
            'appointment_type_id' => $type->appointment_type_id,
            'starts_at' => now()->addHours(24),
            'ends_at' => now()->addHours(25),
            'capacity' => 1,
            'status' => AppointmentSlot::STATUS_OPEN,
        ]);
        AppointmentBooking::create([
            'appointment_slot_id' => $slot->appointment_slot_id,
            'user_id' => $member->user_id,
            'booked_by_user_id' => $member->user_id,
            'status' => AppointmentBooking::STATUS_CONFIRMED,
        ]);

        Artisan::call('pastoral:fire-booking-reminders', ['--lead-hours' => 24]);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $member->user_id)
                ->where('type', 'appointment_booking_reminder')
                ->exists()
        );
    }

    public function test_full_slot_rejects_second_booking_with_validation(): void
    {
        [$church, $priestUser, $roles] = $this->seedPriestChurch();
        $a = $this->createUser(['email' => 'pac3-a@example.com']);
        $b = $this->createUser(['email' => 'pac3-b@example.com']);
        foreach ([$a, $b] as $u) {
            ChurchUser::create([
                'church_id' => $church->church_id,
                'user_id' => $u->user_id,
                'status' => 'active',
                'joined_at' => now(),
            ]);
            UserChurchRole::create([
                'church_id' => $church->church_id,
                'user_id' => $u->user_id,
                'role_id' => $roles['servant']->role_id,
                'assigned_at' => now(),
            ]);
        }

        TenantContext::set($church);
        $priest = Priest::create([
            'user_id' => $priestUser->user_id,
            'status' => Priest::STATUS_ACTIVE,
        ]);
        $type = AppointmentType::create([
            'slug' => 'full',
            'name_ar' => 'ممتلئ',
            'name_en' => 'Full',
            'default_capacity' => 1,
            'default_duration_minutes' => 30,
            'status' => AppointmentType::STATUS_ACTIVE,
        ]);
        $slot = AppointmentSlot::create([
            'priest_id' => $priest->priest_id,
            'appointment_type_id' => $type->appointment_type_id,
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHour(),
            'capacity' => 1,
            'status' => AppointmentSlot::STATUS_OPEN,
        ]);

        $this->actingAs($a)->post(route('church.appointments.book', $slot))->assertRedirect();
        $this->actingAs($b)
            ->from(route('church.appointments.index'))
            ->post(route('church.appointments.book', $slot))
            ->assertRedirect(route('church.appointments.index'))
            ->assertSessionHasErrors('slot');
    }

    /** @return array{0: Church, 1: \App\Models\User, 2: array<string, \App\Models\Role>} */
    private function seedPriestChurch(): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $priestUser = $this->createUser(['email' => 'pac34-priest-'.uniqid('', true).'@example.com']);
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
