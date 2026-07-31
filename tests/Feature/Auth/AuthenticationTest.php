<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

/**
 * OTP-based login: identifier → one-time code → session.
 */
class AuthenticationTest extends EventModuleTestCase
{
    use RefreshDatabase;

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post(route('login'), [
            'identifier' => $user->email,
        ])->assertRedirect(route('login.otp.show'));

        $otp = OtpCode::query()->where('user_id', $user->user_id)->value('code');
        $this->assertNotNull($otp);

        $response = $this->post(route('login.otp.verify'), [
            'otp' => (string) $otp,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();
    }

    public function test_users_can_not_authenticate_with_invalid_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post(route('login'), [
            'identifier' => $user->email,
        ])->assertRedirect(route('login.otp.show'));

        $this->post(route('login.otp.verify'), [
            'otp' => '000000',
        ]);

        $this->assertGuest();
    }

    public function test_unverified_user_is_not_logged_in(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $this->loginWithOtp($user);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect();
    }
}
