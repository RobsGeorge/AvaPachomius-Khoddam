<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        // The home route ('/') is auth-protected, so an unauthenticated visitor is
        // redirected to the login page rather than served a 200.
        $this->get('/')->assertRedirect(route('login'));
    }
}
