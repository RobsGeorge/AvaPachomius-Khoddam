<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\PendingRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Replaces the default Laravel email-verification test (this app has no such flow).
 * Verifies the real OTP step: a correct code consumes the OTP and advances the user
 * to set-password; a wrong code is rejected and the OTP is preserved.
 */
class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_otp_is_accepted_and_advances_to_set_password(): void
    {
        $user = User::factory()->unverified()->create();

        OtpCode::create([
            'user_id' => $user->user_id,
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->post('/verify-otp', [
            'user_id' => $user->user_id,
            'otp' => '123456',
        ])->assertRedirect(route('password.set', ['user_id' => $user->user_id]));

        // The one-time code is consumed on success.
        $this->assertDatabaseMissing('otp_code', ['user_id' => $user->user_id]);
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->get(route('otp.verify', ['user_id' => $user->user_id]))
            ->assertRedirect(route('password.set', ['user_id' => $user->user_id]));
    }

    public function test_resend_after_successful_otp_does_not_send_another_code(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        OtpCode::create([
            'user_id' => $user->user_id,
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->post('/verify-otp', [
            'user_id' => $user->user_id,
            'otp' => '123456',
        ])->assertRedirect(route('password.set', ['user_id' => $user->user_id]));

        $this->post(route('otp.resend'), [
            'user_id' => $user->user_id,
        ])->assertRedirect(route('password.set', ['user_id' => $user->user_id]));

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('otp_code', ['user_id' => $user->user_id]);
        $this->assertSame(
            $user->user_id,
            session(PendingRegistrationService::SESSION_PASSWORD_USER_KEY)
        );
    }

    public function test_an_invalid_otp_is_rejected_and_preserves_the_code(): void
    {
        $user = User::factory()->unverified()->create();

        OtpCode::create([
            'user_id' => $user->user_id,
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->post('/verify-otp', [
            'user_id' => $user->user_id,
            'otp' => '000000',
        ])->assertSessionHasErrors('otp');

        $this->assertDatabaseHas('otp_code', ['user_id' => $user->user_id]);
    }

    public function test_five_digit_otp_shows_arabic_digits_validation(): void
    {
        $user = User::factory()->unverified()->create();

        OtpCode::create([
            'user_id' => $user->user_id,
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['locale' => 'ar', 'user_id' => $user->user_id])
            ->from(route('otp.verify', ['user_id' => $user->user_id]))
            ->post('/verify-otp', [
                'user_id' => $user->user_id,
                'otp' => '12345',
            ])
            ->assertSessionHasErrors('otp');

        $message = session('errors')->first('otp');
        $this->assertStringContainsString('رمز التحقق', $message);
        $this->assertStringContainsString('6', $message);
        $this->assertStringNotContainsString('The otp field', $message);
    }

    public function test_otp_form_uses_six_digit_input_constraints(): void
    {
        $user = User::factory()->unverified()->create();

        $this->withSession(['locale' => 'ar', 'user_id' => $user->user_id])
            ->get(route('otp.verify', ['user_id' => $user->user_id]))
            ->assertOk()
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('pattern="[0-9]{6}"', false)
            ->assertSee('minlength="6"', false)
            ->assertSee('maxlength="6"', false)
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee(__('auth.otp_digits_hint'), false);
    }
}
