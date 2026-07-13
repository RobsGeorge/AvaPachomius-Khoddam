<?php

namespace Tests\Feature\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
}
