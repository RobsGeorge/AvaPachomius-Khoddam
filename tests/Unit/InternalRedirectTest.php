<?php

namespace Tests\Unit;

use App\Support\InternalRedirect;
use Tests\TestCase;

class InternalRedirectTest extends TestCase
{
    public function test_relative_paths_are_kept(): void
    {
        $this->assertSame('/feedback/surveys/3', InternalRedirect::path('/feedback/surveys/3'));
        $this->assertSame('/announcements/9?x=1', InternalRedirect::path('/announcements/9?x=1'));
    }

    public function test_protocol_relative_urls_are_rejected(): void
    {
        $this->assertNull(InternalRedirect::path('//evil.example/phish'));
    }

    public function test_absolute_same_host_urls_become_paths(): void
    {
        config(['app.url' => 'https://portal.example']);

        $this->assertSame(
            '/feedback/surveys/4',
            InternalRedirect::path('https://portal.example/feedback/surveys/4')
        );
    }

    public function test_external_hosts_are_rejected(): void
    {
        config(['app.url' => 'https://portal.example']);

        $this->assertNull(InternalRedirect::path('https://evil.example/feedback/surveys/4'));
    }
}
