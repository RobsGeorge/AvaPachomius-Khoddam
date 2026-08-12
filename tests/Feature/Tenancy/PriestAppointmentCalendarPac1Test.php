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
use App\Services\Pastoral\PriestDelegationService;
use App\Services\RoleTemplateService;
use App\Support\Pastoral\BookingRules;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Tests\Support\EventModuleTestCase;

/**
 * PAC1 — priest_secretary, appointment tables, additive booking columns, delegation policies.
 */
class PriestAppointmentCalendarPac1Test extends EventModuleTestCase
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

    public function test_pac1_tables_and_confession_booking_columns_exist(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('priest_secretary'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('appointment_type'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('appointment_slot'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('appointment_booking'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('confession_booking', 'booked_by_user_id'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('confession_booking', 'rescheduled_from_booking_id'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('confession_booking', 'cancelled_at'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('confession_booking', 'cancelled_by_user_id'));
    }

    public function test_appointment_and_secretary_models_are_isolated_by_church(): void
    {
        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'stmina-pac1', 'name' => 'St Mina PAC1', 'status' => 'active']);

        $userA = $this->createUser(['email' => 'pac1-a@example.com']);
        $userB = $this->createUser(['email' => 'pac1-b@example.com']);

        TenantContext::set($churchA);
        $priestA = Priest::create([
            'user_id' => $userA->user_id,
            'title' => 'Abouna A',
            'status' => Priest::STATUS_ACTIVE,
        ]);
        $typeA = AppointmentType::create([
            'slug' => 'pastoral-meeting',
            'name_ar' => 'لقاء رعوي',
            'name_en' => 'Pastoral meeting',
            'default_capacity' => 1,
            'default_duration_minutes' => 30,
            'status' => AppointmentType::STATUS_ACTIVE,
        ]);
        $slotA = AppointmentSlot::create([
            'priest_id' => $priestA->priest_id,
            'appointment_type_id' => $typeA->appointment_type_id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 2,
            'status' => AppointmentSlot::STATUS_OPEN,
        ]);
        $secA = PriestSecretary::create([
            'priest_id' => $priestA->priest_id,
            'user_id' => $userA->user_id,
            'status' => PriestSecretary::STATUS_ACTIVE,
        ]);
        $bookingA = AppointmentBooking::create([
            'appointment_slot_id' => $slotA->appointment_slot_id,
            'user_id' => $userA->user_id,
            'booked_by_user_id' => $userA->user_id,
            'status' => AppointmentBooking::STATUS_CONFIRMED,
        ]);

        TenantContext::set($churchB);
        $priestB = Priest::create([
            'user_id' => $userB->user_id,
            'title' => 'Abouna B',
            'status' => Priest::STATUS_ACTIVE,
        ]);
        $typeB = AppointmentType::create([
            'slug' => 'pastoral-meeting',
            'name_ar' => 'لقاء',
            'status' => AppointmentType::STATUS_ACTIVE,
        ]);
        AppointmentSlot::create([
            'priest_id' => $priestB->priest_id,
            'appointment_type_id' => $typeB->appointment_type_id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'capacity' => 1,
            'status' => AppointmentSlot::STATUS_OPEN,
        ]);
        PriestSecretary::create([
            'priest_id' => $priestB->priest_id,
            'user_id' => $userB->user_id,
            'status' => PriestSecretary::STATUS_ACTIVE,
        ]);

        $this->assertNull(AppointmentType::find($typeA->appointment_type_id));
        $this->assertNull(AppointmentSlot::find($slotA->appointment_slot_id));
        $this->assertNull(AppointmentBooking::find($bookingA->appointment_booking_id));
        $this->assertNull(PriestSecretary::find($secA->priest_secretary_id));

        TenantContext::set($churchA);
        $this->assertNotNull(AppointmentType::find($typeA->appointment_type_id));
        $this->assertNotNull(AppointmentSlot::find($slotA->appointment_slot_id));
        $this->assertNotNull(AppointmentBooking::find($bookingA->appointment_booking_id));
        $this->assertNotNull(PriestSecretary::find($secA->priest_secretary_id));
        $this->assertNull(Priest::find($priestB->priest_id));
    }

    public function test_secretary_template_includes_delegated_keys(): void
    {
        $templates = app(RoleTemplateService::class)->ensureChurchTemplates()->keyBy(fn ($r) => $r->slug);
        $this->assertArrayHasKey('secretary', $templates->all());

        $keys = $templates['secretary']->permissions()->pluck('permissions.key')->all();
        $this->assertContains('confession.manage_delegated', $keys);
        $this->assertContains('confession.book_on_behalf', $keys);
        $this->assertContains('appointment.manage_delegated', $keys);
        $this->assertContains('appointment.book_on_behalf', $keys);
        $this->assertNotContains('confession.manage', $keys);
    }

    public function test_delegate_can_manage_confession_slots_only_when_assigned(): void
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);

        $priestUser = $this->createUser(['email' => 'pac1-priest@example.com']);
        $secretary = $this->createUser(['email' => 'pac1-sec@example.com']);
        $outsider = $this->createUser(['email' => 'pac1-out@example.com']);

        foreach ([$priestUser, $secretary, $outsider] as $member) {
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
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $secretary->user_id,
            'role_id' => $roles['secretary']->role_id,
            'assigned_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $outsider->user_id,
            'role_id' => $roles['secretary']->role_id,
            'assigned_at' => now(),
        ]);

        TenantContext::set($church);
        $priest = Priest::create([
            'user_id' => $priestUser->user_id,
            'title' => 'Abouna',
            'status' => Priest::STATUS_ACTIVE,
        ]);
        $slot = ConfessionSlot::create([
            'priest_id' => $priest->priest_id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 1,
            'status' => ConfessionSlot::STATUS_OPEN,
        ]);

        $delegation = app(PriestDelegationService::class);
        $this->assertFalse($delegation->canManageConfessionSlots($secretary, $priest));

        PriestSecretary::create([
            'priest_id' => $priest->priest_id,
            'user_id' => $secretary->user_id,
            'status' => PriestSecretary::STATUS_ACTIVE,
        ]);

        $this->assertTrue($delegation->canManageConfessionSlots($secretary, $priest));
        $this->assertFalse($delegation->canManageConfessionSlots($outsider, $priest));
        $this->assertTrue($delegation->canManageConfessionSlots($priestUser, $priest));

        $this->assertTrue(Gate::forUser($secretary)->allows('update', $slot));
        $this->assertFalse(Gate::forUser($outsider)->allows('update', $slot));
    }

    public function test_book_on_behalf_permission_and_booking_columns(): void
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);

        $member = $this->createUser(['email' => 'pac1-member@example.com']);
        $secretary = $this->createUser(['email' => 'pac1-booker@example.com']);
        $priestUser = $this->createUser(['email' => 'pac1-p2@example.com']);

        foreach ([$member, $secretary, $priestUser] as $u) {
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
        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $priestUser->user_id,
            'role_id' => $roles['priest']->role_id,
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

        $this->assertTrue(Gate::forUser($secretary)->allows('bookOnBehalf', ConfessionBooking::class));
        $this->assertFalse(Gate::forUser($member)->allows('bookOnBehalf', ConfessionBooking::class));

        $booking = ConfessionBooking::create([
            'confession_slot_id' => $slot->confession_slot_id,
            'user_id' => $member->user_id,
            'booked_by_user_id' => $secretary->user_id,
            'status' => ConfessionBooking::STATUS_CONFIRMED,
            'notes' => 'On behalf',
        ]);

        $this->assertSame((int) $secretary->user_id, (int) $booking->fresh()->booked_by_user_id);
        $this->assertTrue(Gate::forUser($secretary)->allows('view', $booking));
        $this->assertTrue(Gate::forUser($member)->allows('view', $booking));
    }

    public function test_booking_rules_defaults_from_church_settings(): void
    {
        $church = Church::main();
        $rules = BookingRules::for($church);
        $this->assertSame(BookingRules::DEFAULT_TIMEZONE, $rules->timezone);
        $this->assertSame(60, $rules->minLeadMinutes);
        $this->assertSame(120, $rules->cancelCutoffMinutes);

        $church->settings = [
            'timezone' => 'Europe/Athens',
            'booking' => [
                'min_lead_minutes' => 90,
                'cancel_cutoff_minutes' => 180,
            ],
        ];
        $church->save();

        $custom = BookingRules::for($church->fresh());
        $this->assertSame('Europe/Athens', $custom->timezone);
        $this->assertSame(90, $custom->minLeadMinutes);
        $this->assertSame(180, $custom->cancelCutoffMinutes);
    }
}
