<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\LocaleController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_locale_is_not_found(): void
    {
        $this->get(route('locale.switch', 'fr'))->assertNotFound();
    }

    public function test_guest_locale_cookie_survives_a_new_session(): void
    {
        $this->get(route('locale.switch', 'en'))
            ->assertRedirect()
            ->assertCookie(LocaleController::COOKIE, 'en');

        $this->flushSession();

        $this->withCookie(LocaleController::COOKIE, 'en')
            ->get(route('login'))
            ->assertOk()
            ->assertSee(__('auth.login_title', [], 'en'), false);

        $this->assertSame('en', app()->getLocale());
    }

    public function test_locale_persists_on_the_user_and_survives_logout_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('locale.switch', 'en'))
            ->assertRedirect()
            ->assertCookie(LocaleController::COOKIE, 'en');

        $this->assertSame('en', $user->fresh()->ui_locale);
        $this->assertNull($user->fresh()->communication_locale);

        $this->post(route('logout'))->assertRedirect('/login');
        $this->assertGuest();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('en', session('locale'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.title', [], 'en'), false)
            ->assertDontSee(__('dashboard.title', [], 'ar'), false);

        $this->assertSame('en', app()->getLocale());
    }

    public function test_guest_cookie_is_copied_onto_the_user_at_login(): void
    {
        $user = User::factory()->create(['ui_locale' => null]);

        $this->withCookie(LocaleController::COOKIE, 'en')
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect();

        $this->assertSame('en', $user->fresh()->ui_locale);
        $this->assertSame('en', session('locale'));
    }
}
