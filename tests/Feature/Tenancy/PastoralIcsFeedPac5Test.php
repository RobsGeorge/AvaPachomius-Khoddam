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
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * PAC5 — tokenized ICS feeds (member "my bookings" + priest/secretary agenda).
 */
class PastoralIcsFeedPac5Test extends EventModuleTestCase
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

    public function test_member_sees_subscribe_link_and_feed_contains_confirmed_booking(): void
    {
        [$church, $priestUser] = $this->seedPriestChurch();
        $member = $this->seedMember($church);

        TenantContext::set($church);
        $priest = Priest::create(['user_id' => $priestUser->user_id, 'status' => Priest::STATUS_ACTIVE]);
        $slot = ConfessionSlot::create([
            'priest_id' => $priest->priest_id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'capacity' => 1,
            'status' => ConfessionSlot::STATUS_OPEN,
        ]);
        $booking = ConfessionBooking::create([
            'confession_slot_id' => $slot->confession_slot_id,
            'user_id' => $member->user_id,
            'status' => ConfessionBooking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($member)
            ->get(route('church.confession.my-bookings'))
            ->assertOk()
            ->assertSee(route('church.ics.my-bookings.regenerate'), false);

        $token = $member->fresh()->ics_bookings_token;
        $this->assertNotNull($token);

        $response = $this->get(route('ics.bookings', $token));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('confession-booking-'.$booking->confession_booking_id.'@khedma', $response->getContent());
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
    }

    public function test_member_feed_also_includes_confirmed_appointment_booking(): void
    {
        [$church, $priestUser] = $this->seedPriestChurch();
        $member = $this->seedMember($church);

        TenantContext::set($church);
        $priest = Priest::create(['user_id' => $priestUser->user_id, 'status' => Priest::STATUS_ACTIVE]);
        $type = AppointmentType::create([
            'slug' => 'pac5-meeting',
            'name_ar' => 'لقاء',
            'name_en' => 'Meeting',
            'default_capacity' => 1,
            'default_duration_minutes' => 30,
            'status' => AppointmentType::STATUS_ACTIVE,
        ]);
        $slot = AppointmentSlot::create([
            'priest_id' => $priest->priest_id,
            'appointment_type_id' => $type->appointment_type_id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'capacity' => 1,
            'status' => AppointmentSlot::STATUS_OPEN,
        ]);
        $booking = AppointmentBooking::create([
            'appointment_slot_id' => $slot->appointment_slot_id,
            'user_id' => $member->user_id,
            'status' => AppointmentBooking::STATUS_CONFIRMED,
        ]);

        $token = app(\App\Services\Pastoral\AppointmentIcsFeed::class)->tokenForMember($member);
        $response = $this->get(route('ics.bookings', $token));

        $response->assertOk();
        $this->assertStringContainsString('appointment-booking-'.$booking->appointment_booking_id.'@khedma', $response->getContent());
    }

    public function test_invalid_token_returns_404_for_both_feeds(): void
    {
        $this->get(route('ics.bookings', 'not-a-real-token'))->assertNotFound();
        $this->get(route('ics.priest-agenda', 'not-a-real-token'))->assertNotFound();
    }

    public function test_priest_sees_agenda_link_and_feed_includes_booker_name(): void
    {
        [$church, $priestUser] = $this->seedPriestChurch();
        $member = $this->seedMember($church);

        TenantContext::set($church);
        $priest = Priest::create(['user_id' => $priestUser->user_id, 'status' => Priest::STATUS_ACTIVE]);
        $slot = ConfessionSlot::create([
            'priest_id' => $priest->priest_id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'capacity' => 1,
            'status' => ConfessionSlot::STATUS_OPEN,
        ]);
        ConfessionBooking::create([
            'confession_slot_id' => $slot->confession_slot_id,
            'user_id' => $member->user_id,
            'status' => ConfessionBooking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($priestUser)
            ->get(route('church.confession.index'))
            ->assertOk()
            ->assertSee(route('church.ics.priest-agenda.regenerate', $priest), false);

        $token = $priest->fresh()->ics_agenda_token;
        $this->assertNotNull($token);

        $response = $this->get(route('ics.priest-agenda', $token));
        $response->assertOk();
        $this->assertStringContainsString($member->displayName(), $response->getContent());
    }

    public function test_regenerating_my_bookings_link_invalidates_the_old_one(): void
    {
        [$church] = $this->seedPriestChurch();
        $member = $this->seedMember($church);

        TenantContext::set($church);
        $oldToken = app(\App\Services\Pastoral\AppointmentIcsFeed::class)->tokenForMember($member);

        $this->actingAs($member)
            ->post(route('church.ics.my-bookings.regenerate'))
            ->assertRedirect();

        $this->get(route('ics.bookings', $oldToken))->assertNotFound();

        $newToken = $member->fresh()->ics_bookings_token;
        $this->assertNotSame($oldToken, $newToken);
        $this->get(route('ics.bookings', $newToken))->assertOk();
    }

    public function test_stranger_cannot_regenerate_another_priests_agenda_link(): void
    {
        [$church, $priestUser] = $this->seedPriestChurch();
        $stranger = $this->seedMember($church, 'pac5-stranger@example.com');

        TenantContext::set($church);
        $priest = Priest::create(['user_id' => $priestUser->user_id, 'status' => Priest::STATUS_ACTIVE]);

        $this->actingAs($stranger)
            ->post(route('church.ics.priest-agenda.regenerate', $priest))
            ->assertForbidden();
    }

    public function test_priest_feed_is_tenant_isolated(): void
    {
        [$churchA, $priestUserA] = $this->seedPriestChurch();
        $memberA = $this->seedMember($churchA, 'pac5-member-a@example.com');

        TenantContext::set($churchA);
        $priestA = Priest::create(['user_id' => $priestUserA->user_id, 'status' => Priest::STATUS_ACTIVE]);
        $slotA = ConfessionSlot::create([
            'priest_id' => $priestA->priest_id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'capacity' => 1,
            'status' => ConfessionSlot::STATUS_OPEN,
        ]);
        ConfessionBooking::create([
            'confession_slot_id' => $slotA->confession_slot_id,
            'user_id' => $memberA->user_id,
            'status' => ConfessionBooking::STATUS_CONFIRMED,
        ]);
        $tokenA = app(\App\Services\Pastoral\AppointmentIcsFeed::class)->tokenForPriest($priestA);

        // A second, distinct church: the BelongsToChurch scope on Priest must not resolve
        // Church A's priest/token while the tenant context is Church B (same guarantee the
        // rest of the tenant-isolation suite verifies at the model-scope level).
        $churchB = Church::create(['slug' => 'pac5-iso-'.uniqid('', true), 'name' => 'PAC5 Isolation Church', 'status' => 'active']);
        TenantContext::set($churchB);

        $this->assertNull(Priest::query()->where('ics_agenda_token', $tokenA)->first());
        $this->assertNull(app(\App\Services\Pastoral\AppointmentIcsFeed::class)->icsForPriestToken($tokenA));
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function seedPriestChurch(string $priestEmail = 'pac5-priest@example.com'): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $priestUser = $this->createUser(['email' => str_replace('@', '-'.uniqid('', true).'@', $priestEmail)]);
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

        return [$church, $priestUser];
    }

    private function seedMember(Church $church, string $email = 'pac5-member@example.com'): \App\Models\User
    {
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $member = $this->createUser(['email' => str_replace('@', '-'.uniqid('', true).'@', $email)]);
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

        return $member;
    }
}
