<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_shows_login_at_root_for_guests(): void
    {
        $this->get('/')->assertOk()->assertViewIs('auth.login');
    }
}
