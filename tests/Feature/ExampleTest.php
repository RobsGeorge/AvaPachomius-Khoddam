<?php

namespace Tests\Feature;

use Tests\Support\EventModuleTestCase;

class ExampleTest extends EventModuleTestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        // Unpublished public homepage falls back to login rather than serving a 200.
        $this->get('/')->assertRedirect(route('login'));
    }
}
