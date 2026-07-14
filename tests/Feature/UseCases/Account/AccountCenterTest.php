<?php

namespace Tests\Feature\UseCases\Account;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\EventModuleTestCase;

/**
 * F-03 self-service account center (UC-ACC-03, TC-ACC-03/07). Covers the account
 * hub landing, the first-class password change (success + wrong-current-password),
 * and the personal data export.
 */
class AccountCenterTest extends EventModuleTestCase
{
    public function test_account_center_renders_for_an_authenticated_user(): void
    {
        $user = $this->createUser(['email' => 'acc-center@example.com']);

        $this->actingAs($user)->get(route('account.index'))
            ->assertOk()
            ->assertSee(route('account.password.update'))
            ->assertSee(route('account.export'));
    }

    public function test_guest_cannot_reach_the_account_center(): void
    {
        $this->get(route('account.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_change_their_password_with_the_correct_current_password(): void
    {
        $user = $this->createUser(['email' => 'acc-pw@example.com']); // factory password is 'password'

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ]);

        $response->assertRedirect(route('account.index'));
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewPass1!', $user->fresh()->password));
    }

    public function test_password_change_is_rejected_when_the_current_password_is_wrong(): void
    {
        $user = $this->createUser(['email' => 'acc-pw-bad@example.com']);
        $originalHash = $user->password;

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'not-the-password',
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertSame($originalHash, $user->fresh()->password); // unchanged
    }

    public function test_password_change_enforces_strength_and_confirmation(): void
    {
        $user = $this->createUser(['email' => 'acc-pw-weak@example.com']);

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'weak',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password)); // still the old one
    }

    public function test_data_export_returns_the_users_own_account_json(): void
    {
        $user = $this->createUser(['email' => 'acc-export@example.com', 'job' => 'Deacon']);

        $response = $this->actingAs($user)->get(route('account.export'));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $response->assertJsonPath('profile.email', 'acc-export@example.com');
        $response->assertJsonPath('profile.job', 'Deacon');
    }
}
