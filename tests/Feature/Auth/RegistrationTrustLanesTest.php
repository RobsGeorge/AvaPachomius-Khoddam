<?php

namespace Tests\Feature\Auth;

use App\Mail\SendOTPEmail;
use App\Models\Church;
use App\Models\CourseApplication;
use App\Models\Invitation;
use App\Models\OtpCode;
use App\Models\Person;
use App\Models\RegistrationApplication;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\ChurchRegistrationQrService;
use App\Services\People\InvitationService;
use App\Services\People\PersonDuplicateDetector;
use App\Services\People\PersonRegistryService;
use App\Services\RegistrationApplicationService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Support\EventModuleTestCase;

/**
 * Trust lanes on top of the live registration flow: invite (pre-approved),
 * QR+token, and open self-serve (OTP before queue; Person deferred to approval).
 */
class RegistrationTrustLanesTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $church = Church::main() ?? $this->createChurch();
        TenantContext::set($church);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'national_id' => '29001011234567',
            'email' => 'open-lane@example.co',
            'job' => 'Servant',
            'date_of_birth' => '2000-01-01',
            'mobile_number' => '1012345678',
        ], $overrides);
    }

    public function test_invite_accept_creates_no_approval_queue_rows(): void
    {
        Mail::fake();
        $church = Church::main() ?? $this->createChurch();
        TenantContext::set($church);

        $person = app(PersonRegistryService::class)->createPerson([
            'church_id' => $church->church_id,
            'first_name' => 'مدعو',
            'second_name' => 'اختبار',
            'email' => 'invite-lane@example.com',
            'mobile_number' => '01000000991',
        ], true);

        $result = app(InvitationService::class)->invite($person, [
            'send_email' => true,
        ]);

        $otp = OtpCode::find($result['user']->user_id);
        $this->assertNotNull($otp);
        $this->assertTrue(app(InvitationService::class)->verifyOtp($result['invitation'], (string) $otp->code));

        $beforeReg = RegistrationApplication::query()->count();
        $beforeCourse = CourseApplication::query()->count();

        $user = app(InvitationService::class)->accept($result['invitation']->fresh(), 'password123');

        $this->assertTrue((bool) $user->is_verified);
        $this->assertSame(User::APPLICATION_STATUS_APPROVED, $user->application_status);
        $this->assertSame(User::REGISTRATION_LANE_INVITE, $user->registration_lane);
        $this->assertSame($beforeReg, RegistrationApplication::query()->count());
        $this->assertSame($beforeCourse, CourseApplication::query()->count());
        $this->assertSame(Invitation::STATUS_ACCEPTED, $result['invitation']->fresh()->status);
    }

    public function test_open_lane_before_otp_creates_no_person_and_no_queue_rows(): void
    {
        Mail::fake();
        $peopleBefore = Person::withoutTenancy()->count();

        $this->post(route('register.store'), $this->registerPayload())
            ->assertRedirect();

        $user = User::where('email', 'open-lane@example.co')->first();
        $this->assertNotNull($user);
        $this->assertFalse((bool) $user->registration_completed);
        $this->assertSame(User::REGISTRATION_LANE_OPEN, $user->registration_lane);
        $this->assertNull($user->person_id);
        $this->assertSame($peopleBefore, Person::withoutTenancy()->count());
        $this->assertSame(0, RegistrationApplication::query()->where('user_id', $user->user_id)->count());
        $this->assertSame(0, CourseApplication::query()->where('user_id', $user->user_id)->count());
        Mail::assertSent(SendOTPEmail::class);
    }

    public function test_open_lane_person_created_on_admin_approval(): void
    {
        Mail::fake();

        $this->post(route('register.store'), $this->registerPayload([
            'email' => 'approve-person@example.co',
            'national_id' => '29001011234568',
            'mobile_number' => '1012345679',
        ]))->assertRedirect();

        $user = User::where('email', 'approve-person@example.co')->firstOrFail();
        $this->assertNull($user->person_id);

        $user->forceFill([
            'registration_completed' => true,
            'application_status' => RegistrationApplication::STATUS_PENDING_REVIEW,
        ])->save();

        $application = app(RegistrationApplicationService::class)->createFromUser($user);
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'approver-trust@example.com']);

        $course = $this->createCourse();
        $role = Role::create([
            'role_name' => 'Student',
            'role_decription' => 'Student',
            'slug' => 'student',
            'course_id' => $course->course_id,
            'is_template' => false,
        ]);

        app(\App\Services\RegistrationReviewService::class)->approve(
            $application,
            $admin,
            [],
            [['course_id' => $course->course_id, 'role_id' => $role->role_id]],
            true,
        );

        $user->refresh();
        $this->assertNotNull($user->person_id);
        $this->assertNotNull(Person::withoutTenancy()->find($user->person_id));
    }

    public function test_qr_expired_or_invalid_token_blocked(): void
    {
        Mail::fake();
        $church = Church::main() ?? $this->createChurch();
        TenantContext::set($church);

        $this->get(route('register.qr.scan', ['token' => 'not-a-real-token']))
            ->assertRedirect(route('register'));

        $minted = app(ChurchRegistrationQrService::class)->mint($church, null, 7);
        $token = $minted['token'];
        $token->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get(route('register.qr.scan', ['token' => $minted['plain_token']]))
            ->assertRedirect(route('register'));

        $this->withSession([
            ChurchRegistrationQrService::SESSION_LANE => User::REGISTRATION_LANE_QR,
            ChurchRegistrationQrService::SESSION_TOKEN_ID => $token->church_registration_qr_token_id,
        ])->post(route('register.store'), $this->registerPayload([
            'email' => 'qr-expired@example.co',
            'national_id' => '29001011234569',
            'mobile_number' => '1012345680',
        ]))->assertRedirect();

        $this->assertNull(User::where('email', 'qr-expired@example.co')->first());
    }

    public function test_qr_valid_token_tags_registration_via_qr(): void
    {
        Mail::fake();
        $church = Church::main() ?? $this->createChurch();
        TenantContext::set($church);

        $minted = app(ChurchRegistrationQrService::class)->mint($church);
        $this->get(route('register.qr.scan', ['token' => $minted['plain_token']]))
            ->assertRedirect(route('register'));

        $this->post(route('register.store'), $this->registerPayload([
            'email' => 'qr-ok@example.co',
            'national_id' => '29001011234570',
            'mobile_number' => '1012345681',
        ]))->assertRedirect();

        $user = User::where('email', 'qr-ok@example.co')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::REGISTRATION_LANE_QR, $user->registration_lane);
        $this->assertSame(
            $minted['token']->church_registration_qr_token_id,
            (int) $user->registration_qr_token_id
        );
        $this->assertNull($user->person_id);
    }

    public function test_open_lane_store_is_throttled_with_real_429(): void
    {
        Mail::fake();

        $route = Route::getRoutes()->getByName('register.store');
        $this->assertNotNull($route);
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());

        $last = null;
        for ($i = 1; $i <= 31; $i++) {
            $last = $this->post(route('register.store'), $this->registerPayload([
                'email' => "throttle{$i}@example.co",
                'national_id' => sprintf('2900101%07d', $i),
                'mobile_number' => sprintf('10%08d', $i),
            ]));
        }

        $this->assertNotNull($last);
        $this->assertSame(429, $last->getStatusCode());
    }

    public function test_soft_dedup_confirm_path_unchanged(): void
    {
        Mail::fake();
        $church = Church::main() ?? $this->createChurch();

        app(PersonRegistryService::class)->createPerson([
            'church_id' => $church->church_id,
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'date_of_birth' => '2000-01-01',
            'mobile_number' => '1012345678',
        ], true);

        $matches = app(PersonDuplicateDetector::class)->findPossibleMatches([
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'date_of_birth' => '2000-01-01',
            'mobile_number' => '1012345678',
        ]);
        $this->assertFalse($matches->isEmpty());

        $this->post(route('register.store'), $this->registerPayload([
            'email' => 'dedup-new@example.co',
            'national_id' => '29001011234571',
            'mobile_number' => '1012345682',
        ]))->assertOk(); // duplicate-confirm view

        $this->assertNull(User::where('email', 'dedup-new@example.co')->first());

        $this->post(route('register.store'), $this->registerPayload([
            'email' => 'dedup-new@example.co',
            'national_id' => '29001011234571',
            'mobile_number' => '1012345682',
            'confirm_possible_duplicate' => '1',
        ]))->assertRedirect();

        $user = User::where('email', 'dedup-new@example.co')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->person_id);
    }
}
