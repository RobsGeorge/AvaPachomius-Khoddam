<?php

namespace Tests\Unit;

use App\Services\PublicHttpUrlService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicHttpUrlServiceTest extends TestCase
{
    public function test_rejects_plain_text_that_is_not_a_url(): void
    {
        $this->expectException(ValidationException::class);
        app(PublicHttpUrlService::class)->assertPublicHttpUrl('just some notes');
    }

    public function test_rejects_non_http_schemes(): void
    {
        $this->expectException(ValidationException::class);
        app(PublicHttpUrlService::class)->assertPublicHttpUrl('ftp://files.example.com/video');
    }

    public function test_rejects_loopback_hosts(): void
    {
        $this->expectException(ValidationException::class);
        app(PublicHttpUrlService::class)->assertPublicHttpUrl('http://127.0.0.1/secret');
    }

    public function test_rejects_unauthorized_public_hosts(): void
    {
        Http::fake(['*' => Http::response('', 403)]);

        try {
            app(PublicHttpUrlService::class)->assertPublicHttpUrl('https://example.com/private');
            $this->fail('Expected a validation error for a private link.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('link_url', $e->errors());
        }
    }

    public function test_accepts_a_public_http_url(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        app(PublicHttpUrlService::class)->assertPublicHttpUrl('https://example.com/video');
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/video');
    }
}
