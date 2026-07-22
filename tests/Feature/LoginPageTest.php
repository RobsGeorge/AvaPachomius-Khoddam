<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads_for_guests(): void
    {
        // The app is Arabic-primary (config app.locale = ar), so pin the locale to
        // English via the session before asserting the English login title.
        $this->withSession(['locale' => 'en'])
            ->get(route('login'))
            ->assertOk()
            ->assertSee(__('auth.login_title', [], 'en'), false);
    }
}
