<?php

namespace Tests\Feature\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * The real password-reset request flow: POST to `password.email`, which sends the
 * app's custom ResetPasswordMail (via User::sendPasswordResetNotification) only
 * when the email belongs to an existing user.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_email_is_sent_for_a_known_address(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect();

        Mail::assertSent(
            ResetPasswordMail::class,
            fn (ResetPasswordMail $mail) => $mail->hasTo($user->email)
        );
    }

    public function test_no_reset_link_is_sent_for_an_unknown_address(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'known@example.co']);

        $this->post(route('password.email'), ['email' => 'stranger@example.co'])
            ->assertRedirect();

        Mail::assertNotSent(ResetPasswordMail::class);
    }

    public function test_invalid_reset_token_uses_localized_passwords_token_in_arabic(): void
    {
        $user = User::factory()->create();

        $this->withSession(['locale' => 'ar'])
            ->from(route('password.reset', ['token' => 'invalid-token']))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'NewPass1!',
                'password_confirmation' => 'NewPass1!',
            ])
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertSame('رمز إعادة تعيين كلمة المرور غير صالح.', __('passwords.token'));
    }

    public function test_invalid_reset_token_uses_localized_passwords_token_in_english(): void
    {
        $user = User::factory()->create();

        $this->withSession(['locale' => 'en'])
            ->from(route('password.reset', ['token' => 'invalid-token']))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'NewPass1!',
                'password_confirmation' => 'NewPass1!',
            ])
            ->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertSame('This password reset token is invalid.', __('passwords.token'));
    }

    public function test_reused_reset_token_uses_localized_passwords_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ];

        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPass1!', $user->fresh()->password));

        $this->withSession(['locale' => 'ar'])
            ->from(route('password.reset', ['token' => $token]))
            ->post(route('password.update'), $payload)
            ->assertSessionHasErrors(['email' => __('passwords.token')]);
    }
}
