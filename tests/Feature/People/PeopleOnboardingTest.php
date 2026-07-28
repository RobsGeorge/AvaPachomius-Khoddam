<?php

namespace Tests\Feature\People;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Course;
use App\Models\Invitation;
use App\Models\OtpCode;
use App\Models\Person;
use App\Models\PersonPlacement;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserServiceRole;
use App\Services\People\InvitationService;
use App\Services\People\PeopleImportService;
use App\Services\People\PersonPlacementService;
use App\Services\People\PersonRegistryService;
use App\Services\People\PortalAccountPreferenceResolver;
use App\Services\RoleTemplateService;
use App\Support\People\PlacementMode;
use App\Support\People\PortalAccountPreference;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class PeopleOnboardingTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_schema_has_onboarding_tables_and_preference_columns(): void
    {
        $this->assertTrue(Schema::hasTable('person_placements'));
        $this->assertTrue(Schema::hasTable('invitations'));
        $this->assertTrue(Schema::hasTable('people_import_batches'));
        $this->assertTrue(Schema::hasTable('people_import_rows'));
        $this->assertTrue(Schema::hasColumn('service', 'portal_account_preference'));
        $this->assertTrue(Schema::hasColumn('course', 'portal_account_preference'));
        $this->assertTrue(Schema::hasColumn('user', 'email_verified_at'));
        $this->assertTrue(Schema::hasColumn('user', 'mobile_verified_at'));
    }

    public function test_placement_is_church_scoped_and_info_only_by_default(): void
    {
        $churchA = Church::main();
        $churchB = $this->createChurch(['slug' => 'people-ob-b', 'name' => 'People OB B']);
        $serviceA = $this->createService(['church_id' => $churchA->church_id, 'title' => 'Svc A']);
        app(RoleTemplateService::class)->cloneTemplatesIntoService($serviceA);

        TenantContext::set($churchA);
        $person = app(PersonRegistryService::class)->createPerson([
            'church_id' => $churchA->church_id,
            'first_name' => 'Info',
            'second_name' => 'Only',
            'email' => 'info-only@example.com',
            'mobile_number' => '01000000001',
        ], true);

        $placement = app(PersonPlacementService::class)->place($person, $serviceA);
        $this->assertSame(PlacementMode::INFO_ONLY, $placement->placement_mode);
        $this->assertSame((int) $churchA->church_id, (int) $placement->church_id);

        TenantContext::set($churchB);
        $this->assertSame(0, PersonPlacement::query()->count());

        TenantContext::set($churchA);
        $this->assertSame(1, PersonPlacement::query()->count());
    }

    public function test_portal_preference_inherits_from_service_to_course(): void
    {
        $service = $this->createService([
            'church_id' => Church::main()->church_id,
            'portal_account_preference' => PortalAccountPreference::PREFER_PORTAL,
        ]);
        $course = $this->createCourse(['service_id' => $service->service_id]);

        $resolver = app(PortalAccountPreferenceResolver::class);
        $this->assertTrue($resolver->defaultsInviteToPortal($course, $service));

        $course->update(['portal_account_preference' => PortalAccountPreference::PREFER_INFO_ONLY]);
        $this->assertFalse($resolver->defaultsInviteToPortal($course->fresh(), $service));
    }

    public function test_invite_creates_pending_user_church_invited_and_email_otp(): void
    {
        Mail::fake();

        $church = Church::main();
        $person = app(PersonRegistryService::class)->createPerson([
            'church_id' => $church->church_id,
            'first_name' => 'Invite',
            'second_name' => 'Me',
            'email' => 'invite-me@example.com',
            'mobile_number' => '01000000002',
        ], true);

        $result = app(InvitationService::class)->invite($person, [
            'send_email' => true,
            'send_whatsapp' => false,
        ]);

        $invitation = $result['invitation'];
        $user = $result['user'];

        $this->assertSame(Invitation::STATUS_PENDING, $invitation->status);
        $this->assertFalse((bool) $user->registration_completed);
        $this->assertSame('invited', ChurchUser::where('user_id', $user->user_id)->where('church_id', $church->church_id)->value('status'));
        $this->assertNotNull(OtpCode::find($user->user_id));
        Mail::assertSent(\App\Mail\PeopleInvitationMail::class);
    }

    public function test_invite_links_existing_incomplete_user_by_email(): void
    {
        Mail::fake();
        $church = Church::main();

        $existing = $this->createUser([
            'email' => 'link-me@example.com',
            'registration_completed' => false,
            'is_verified' => false,
        ]);
        $personId = $existing->person_id;
        $person = Person::withoutTenancy()->find($personId);
        $person->forceFill(['email' => 'link-me@example.com'])->save();

        $result = app(InvitationService::class)->invite($person->fresh(), [
            'send_email' => true,
        ]);

        $this->assertSame((int) $existing->user_id, (int) $result['user']->user_id);
    }

    public function test_accept_invitation_activates_membership_and_assigns_course_role(): void
    {
        Mail::fake();
        $church = Church::main();
        $service = $this->createService(['church_id' => $church->church_id]);
        app(RoleTemplateService::class)->cloneTemplatesIntoService($service);
        $course = $this->createCourse(['service_id' => $service->service_id, 'church_id' => $church->church_id]);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);
        $student = Role::withoutTenancy()->where('course_id', $course->course_id)->where('slug', 'student')->first();
        $this->assertNotNull($student);

        $person = app(PersonRegistryService::class)->createPerson([
            'church_id' => $church->church_id,
            'first_name' => 'Claim',
            'second_name' => 'User',
            'email' => 'claim-user@example.com',
            'mobile_number' => '01000000003',
        ], true);

        $placement = app(PersonPlacementService::class)->place(
            $person,
            $service,
            $course,
            $student,
            PlacementMode::PORTAL_PENDING,
        );

        $result = app(InvitationService::class)->invite($person, [
            'send_email' => true,
            'service_id' => $service->service_id,
            'course_id' => $course->course_id,
            'intended_role_id' => $student->role_id,
            'person_placement_id' => $placement->person_placement_id,
        ]);

        $invitation = $result['invitation'];
        $otp = OtpCode::find($result['user']->user_id);
        $this->assertTrue(app(InvitationService::class)->verifyOtp($invitation, (string) $otp->code));

        $user = app(InvitationService::class)->accept($invitation->fresh(), 'password123');
        $this->assertTrue((bool) $user->registration_completed);
        $this->assertSame('active', ChurchUser::where('user_id', $user->user_id)->value('status'));
        $this->assertTrue(UserCourseRole::where('user_id', $user->user_id)->where('course_id', $course->course_id)->exists());
        $this->assertTrue(UserServiceRole::where('user_id', $user->user_id)->where('service_id', $service->service_id)->exists());
        $this->assertSame(PlacementMode::PORTAL_ACTIVE, $placement->fresh()->placement_mode);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_csv_import_commit_and_bulk_invite(): void
    {
        Mail::fake();
        $church = Church::main();
        TenantContext::set($church);
        $service = $this->createService(['church_id' => $church->church_id, 'slug' => 'import-svc']);
        app(RoleTemplateService::class)->cloneTemplatesIntoService($service);

        $csv = implode("\n", [
            'first_name,second_name,third_name,date_of_birth,gender,email,mobile_number,national_id,service_slug,course_id,unit_anchor_or_code,role_slug,portal_intent',
            'Bulk,Admin,,1990-01-01,male,bulk-admin@example.com,01000000004,,import-svc,,,service-admin,invite_later',
            'Bulk,Member,,1991-01-01,female,bulk-member@example.com,01000000005,,import-svc,,,service-member,info_only',
        ]);
        $file = UploadedFile::fake()->createWithContent('people.csv', $csv);

        $imports = app(PeopleImportService::class);
        $batch = $imports->preview($file, (int) $church->church_id, null, $service->service_id);
        $this->assertSame(2, $batch->row_count);

        $committed = $imports->commit($batch->fresh(['rows']));
        $this->assertSame('committed', $committed->status);
        $this->assertGreaterThanOrEqual(1, $committed->created_count + $committed->linked_count);
        $this->assertSame(2, PersonPlacement::withoutTenancy()->where('service_id', $service->service_id)->count());

        $rowIds = $committed->rows()->whereNotNull('person_id')->pluck('people_import_row_id')->all();
        $result = $imports->bulkInvite($committed, $rowIds, true, false);
        $this->assertGreaterThanOrEqual(1, $result['sent']);
        $this->assertTrue(Invitation::withoutTenancy()->where('church_id', $church->church_id)->exists());
    }

    public function test_find_or_create_by_identity_links_on_email(): void
    {
        $church = Church::main();
        $first = app(PersonRegistryService::class)->createPerson([
            'church_id' => $church->church_id,
            'first_name' => 'Same',
            'second_name' => 'Email',
            'email' => 'same-email@example.com',
            'mobile_number' => '01000000006',
        ], true);

        $second = app(PersonRegistryService::class)->findOrCreateByIdentity([
            'church_id' => $church->church_id,
            'first_name' => 'Same',
            'second_name' => 'Updated',
            'email' => 'same-email@example.com',
            'mobile_number' => '01000000007',
        ], true);

        $this->assertTrue($second['linked']);
        $this->assertSame((int) $first->person_id, (int) $second['person']->person_id);
        $this->assertSame('Updated', $second['person']->second_name);
    }

    public function test_claim_http_flow(): void
    {
        Mail::fake();
        $church = Church::main();
        $person = app(PersonRegistryService::class)->createPerson([
            'church_id' => $church->church_id,
            'first_name' => 'Http',
            'second_name' => 'Claim',
            'email' => 'http-claim@example.com',
            'mobile_number' => '01000000008',
        ], true);

        $result = app(InvitationService::class)->invite($person, ['send_email' => true]);
        $token = $result['plain_token'];
        $otp = OtpCode::find($result['user']->user_id)->code;

        $this->get(route('invitations.claim', $token))->assertOk();
        $this->post(route('invitations.verify-otp', $token), ['otp' => (string) $otp])
            ->assertRedirect(route('invitations.claim', $token));

        $this->withSession(['invitation_otp_ok_'.$result['invitation']->invitation_id => true])
            ->post(route('invitations.accept', $token), [
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertSame(Invitation::STATUS_ACCEPTED, $result['invitation']->fresh()->status);
    }

    public function test_people_index_requires_permission(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->get(route('people.index'))->assertForbidden();

        $admin = $this->createUser(['is_superadmin' => true]);
        $this->actingAs($admin)->get(route('people.index'))->assertOk();
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        Mail::fake();
        $church = Church::main();
        $person = app(PersonRegistryService::class)->createPerson([
            'church_id' => $church->church_id,
            'first_name' => 'Expired',
            'second_name' => 'Invite',
            'email' => 'expired-invite@example.com',
            'mobile_number' => '01000000009',
        ], true);

        $result = app(InvitationService::class)->invite($person, ['send_email' => true]);
        $invitation = $result['invitation'];
        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(InvitationService::class)->accept($invitation->fresh(), 'password123');
    }

    public function test_resend_revokes_previous_pending_invitation(): void
    {
        Mail::fake();
        $church = Church::main();
        $person = app(PersonRegistryService::class)->createPerson([
            'church_id' => $church->church_id,
            'first_name' => 'Resend',
            'second_name' => 'Invite',
            'email' => 'resend-invite@example.com',
            'mobile_number' => '01000000010',
        ], true);

        $first = app(InvitationService::class)->invite($person, ['send_email' => true]);
        $second = app(InvitationService::class)->invite($person, ['send_email' => true]);

        $this->assertSame(Invitation::STATUS_REVOKED, $first['invitation']->fresh()->status);
        $this->assertSame(Invitation::STATUS_PENDING, $second['invitation']->fresh()->status);
    }
}
