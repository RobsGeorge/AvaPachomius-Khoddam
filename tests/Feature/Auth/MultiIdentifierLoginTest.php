<?php

namespace Tests\Feature\Auth;

use App\Models\Church;
use App\Models\OtpCode;
use App\Models\Person;
use App\Models\User;
use App\Services\Auth\LoginOtpChallengeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class MultiIdentifierLoginTest extends EventModuleTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.whatsapp.api_url' => 'https://graph.facebook.com/v18.0',
            'notifications.whatsapp.api_token' => 'test-token',
            'notifications.whatsapp.phone_number_id' => '1234567890',
        ]);
    }

    public function test_email_identifier_issues_otp_and_logs_in(): void
    {
        Mail::fake();

        $user = $this->createUser(['email' => 'otp-email@example.com']);

        $this->post(route('login'), ['identifier' => $user->email])
            ->assertRedirect(route('login.otp.show'));

        $otp = OtpCode::query()->where('user_id', $user->user_id)->first();
        $this->assertNotNull($otp);

        $this->post(route('login.otp.verify'), ['otp' => (string) $otp->code])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verified_mobile_identifier_issues_whatsapp_otp_and_logs_in(): void
    {
        Http::fake([
            '*/messages' => Http::response(['messages' => [['id' => 'wamid.test123']]], 200),
        ]);

        $user = $this->createUser([
            'email' => 'otp-mobile@example.com',
            'mobile_number' => '01099887766',
            'mobile_verified_at' => now(),
        ]);

        $this->post(route('login'), ['identifier' => '01099887766'])
            ->assertRedirect(route('login.otp.show'))
            ->assertSessionHas(LoginOtpChallengeService::SESSION_CHANNEL_KEY, 'mobile');

        Http::assertSentCount(1);

        $otp = OtpCode::query()->where('user_id', $user->user_id)->first();
        $this->assertNotNull($otp);

        $this->post(route('login.otp.verify'), ['otp' => (string) $otp->code])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_mobile_returns_opaque_response_without_otp(): void
    {
        Http::fake();
        Mail::fake();

        $user = $this->createUser([
            'email' => 'unverified-mobile@example.com',
            'mobile_number' => '01011223344',
            'mobile_verified_at' => null,
        ]);

        $this->post(route('login'), ['identifier' => '01011223344'])
            ->assertRedirect(route('login.otp.show'))
            ->assertSessionMissing(LoginOtpChallengeService::SESSION_USER_KEY);

        $this->assertNull(OtpCode::query()->where('user_id', $user->user_id)->first());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_people_can_share_mobile_while_login_resolves_verified_user(): void
    {
        Http::fake([
            '*/messages' => Http::response(['messages' => [['id' => 'wamid.test123']]], 200),
        ]);

        $sharedMobile = '01055443322';

        Person::withoutTenancy()->create([
            'church_id' => Church::main()->church_id,
            'first_name' => 'Shared',
            'second_name' => 'Person',
            'mobile_number' => $sharedMobile,
        ]);

        $user = $this->createUser([
            'email' => 'verified-shared@example.com',
            'mobile_number' => '1055443322',
            'mobile_verified_at' => now(),
        ]);

        $this->post(route('login'), ['identifier' => $sharedMobile])
            ->assertRedirect(route('login.otp.show'))
            ->assertSessionHas(LoginOtpChallengeService::SESSION_USER_KEY, $user->user_id);
    }

    public function test_duplicate_user_mobile_raises_query_exception(): void
    {
        $this->createUser([
            'email' => 'first-mobile@example.com',
            'mobile_number' => '01077776666',
        ]);

        $this->expectException(QueryException::class);

        User::create([
            'first_name' => 'Dup',
            'second_name' => 'User',
            'third_name' => 'X',
            'profile_photo' => '',
            'national_id' => '29001011234568',
            'mobile_number' => '01077776666',
            'email' => 'second-mobile@example.com',
            'job' => 'Servant',
            'date_of_birth' => '2000-01-01',
            'password' => bcrypt('password'),
            'is_verified' => true,
            'registration_completed' => true,
            'application_status' => User::APPLICATION_STATUS_APPROVED,
        ]);
    }

    public function test_changing_mobile_number_clears_mobile_verified_at(): void
    {
        $user = $this->createUser([
            'mobile_number' => '01012345678',
            'mobile_verified_at' => now(),
        ]);

        $user->mobile_number = '01087654321';
        $user->save();

        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_api_otp_login_issues_and_verifies_token(): void
    {
        Mail::fake();

        $user = $this->createUser(['email' => 'api-otp@example.com']);

        $this->postJson('/api/v1/login', [
            'identifier' => $user->email,
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonPath('channel', 'email');

        $otp = OtpCode::query()->where('user_id', $user->user_id)->value('code');
        $this->assertNotNull($otp);

        $response = $this->postJson('/api/v1/login/verify', [
            'identifier' => $user->email,
            'otp' => (string) $otp,
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['user_id', 'email']]);

        $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
}
