<?php

namespace Tests\Feature\Auth;

use App\Mail\SendOTPEmail;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\PendingRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_persisted_email_verified_at_does_not_mint_a_password_session(): void
    {
        Mail::fake();

        $user = $this->pendingUserAfterOtp();

        $this->post(route('register.store'), $this->registerForm($user))
            ->assertRedirect(route('otp.verify', ['user_id' => $user->user_id]));

        $this->assertFalse(PendingRegistrationService::hasCompletedOtpChallenge($user->fresh()));
        $this->assertNotEquals(
            $user->user_id,
            session(PendingRegistrationService::SESSION_PASSWORD_USER_KEY)
        );

        $this->post(route('password.set.store'), [
            'user_id' => $user->user_id,
            'password' => 'AttackerPass1!',
            'password_confirmation' => 'AttackerPass1!',
        ])->assertRedirect(route('otp.verify', ['user_id' => $user->user_id]));

        $this->assertTrue(Hash::check('VictimPass1!', $user->fresh()->password));
        Mail::assertSent(SendOTPEmail::class, fn (SendOTPEmail $mail) => $mail->hasTo('victim@example.com'));
    }

    public function test_pending_lookup_by_mobile_cannot_take_over_a_verified_signup(): void
    {
        Mail::fake();

        $user = $this->pendingUserAfterOtp();

        $this->post(route('register.store'), $this->registerForm($user, [
            'email' => 'attacker@example.com',
            'national_id' => '29101011234567',
        ]))->assertRedirect(route('otp.verify', ['user_id' => $user->user_id]));

        $user->refresh();
        $this->assertSame('victim@example.com', $user->email);
        $this->assertSame('1012345678', $user->mobile_number);
        $this->assertSame('29001011234567', $user->national_id);
        $this->assertFalse(PendingRegistrationService::hasCompletedOtpChallenge($user));
        $this->assertTrue(Hash::check('VictimPass1!', $user->password));

        Mail::assertSent(SendOTPEmail::class, fn (SendOTPEmail $mail) => $mail->hasTo('victim@example.com'));
        Mail::assertNotSent(SendOTPEmail::class, fn (SendOTPEmail $mail) => $mail->hasTo('attacker@example.com'));
    }

    public function test_same_session_register_repost_after_otp_resumes_without_a_new_code(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'national_id' => '29001011234567',
            'email' => 'same-session@example.com',
            'mobile_number' => '1012345678',
        ]);

        OtpCode::create([
            'user_id' => $user->user_id,
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->post('/verify-otp', [
            'user_id' => $user->user_id,
            'otp' => '123456',
        ])->assertRedirect(route('password.set', ['user_id' => $user->user_id]));

        $this->post(route('register.store'), $this->registerForm($user->fresh()))
            ->assertRedirect(route('password.set', ['user_id' => $user->user_id]));

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('otp_code', ['user_id' => $user->user_id]);
        $this->assertSame(
            $user->user_id,
            session(PendingRegistrationService::SESSION_PASSWORD_USER_KEY)
        );
    }

    public function test_otp_resend_from_a_new_browser_after_verify_does_not_skip_otp(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'email' => 'lost-session@example.com',
        ]);

        OtpCode::create([
            'user_id' => $user->user_id,
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->post('/verify-otp', [
            'user_id' => $user->user_id,
            'otp' => '123456',
        ])->assertRedirect(route('password.set', ['user_id' => $user->user_id]));

        $this->flushSession();

        $response = $this->post(route('otp.resend'), [
            'user_id' => $user->user_id,
        ]);

        $response->assertRedirect();
        $this->assertNotEquals(
            route('password.set', ['user_id' => $user->user_id]),
            $response->headers->get('Location')
        );
        $this->assertFalse(PendingRegistrationService::hasCompletedOtpChallenge($user->fresh()));
        $this->assertNotEquals(
            $user->user_id,
            session(PendingRegistrationService::SESSION_PASSWORD_USER_KEY)
        );
        Mail::assertSent(SendOTPEmail::class);
        $this->assertDatabaseHas('otp_code', ['user_id' => $user->user_id]);
    }

    private function pendingUserAfterOtp(array $overrides = []): User
    {
        return User::factory()->unverified()->create(array_merge([
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'national_id' => '29001011234567',
            'email' => 'victim@example.com',
            'mobile_number' => '1012345678',
            'job' => 'Servant',
            'date_of_birth' => '2000-01-01',
            'email_verified_at' => now(),
            'password' => Hash::make('VictimPass1!'),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function registerForm(User $user, array $overrides = []): array
    {
        $dob = $user->date_of_birth;

        return array_merge([
            'first_name' => $user->first_name,
            'second_name' => $user->second_name,
            'third_name' => $user->third_name,
            'national_id' => $user->national_id,
            'email' => $user->email,
            'job' => $user->job,
            'date_of_birth' => $dob instanceof \DateTimeInterface ? $dob->format('Y-m-d') : $dob,
            'mobile_number' => $user->mobile_number,
        ], $overrides);
    }
}
