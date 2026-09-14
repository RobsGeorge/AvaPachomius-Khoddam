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

    public function test_emailed_link_form_then_submit_resets_the_password(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'Reset.Target@Example.com',
            'password' => Hash::make('OldPass1!'),
        ]);

        $this->post(route('password.email'), ['email' => 'reset.target@example.com'])
            ->assertRedirect();

        $resetUrl = $this->resetUrlFromMail($user->email);
        $token = $this->tokenFromResetUrl($resetUrl);

        $this->get($resetUrl)
            ->assertOk()
            ->assertSee('name="token"', false)
            ->assertSee($token, false)
            ->assertSee('reset.target@example.com', false);

        $this->from($resetUrl)
            ->post(route('password.update'), [
                'token' => $token,
                'email' => 'Reset.Target@Example.com',
                'password' => 'NewPass1!',
                'password_confirmation' => 'NewPass1!',
            ])
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPass1!', $user->fresh()->password));
    }

    public function test_uppercased_and_bidi_wrapped_tokens_still_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'Bidi.Reset@Example.com',
            'password' => Hash::make('OldPass1!'),
        ]);
        $token = Password::broker()->createToken($user);
        $mangled = "\u{202B}".strtoupper($token)."\u{202C}";

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $mangled,
                'email' => 'bidi.reset@example.com',
                'password' => 'NewPass1!',
                'password_confirmation' => 'NewPass1!',
            ])
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPass1!', $user->fresh()->password));
    }

    public function test_reset_email_marks_the_link_ltr(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'dir="ltr"')
                && str_contains($html, 'unicode-bidi:isolate');
        });
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

    private function resetUrlFromMail(string $to): string
    {
        $resetUrl = null;
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use (&$resetUrl, $to) {
            $resetUrl = $mail->resetUrl;

            return $mail->hasTo($to);
        });

        $this->assertNotEmpty($resetUrl);

        return $resetUrl;
    }

    private function tokenFromResetUrl(string $resetUrl): string
    {
        $path = parse_url($resetUrl, PHP_URL_PATH);
        $this->assertIsString($path);
        $token = basename($path);
        $this->assertNotSame('', $token);

        return $token;
    }
}
