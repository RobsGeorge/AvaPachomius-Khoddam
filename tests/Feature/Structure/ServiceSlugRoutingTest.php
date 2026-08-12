<?php

namespace Tests\Feature\Structure;

use App\Models\ChurchService;
use App\Services\ServiceContextService;
use Illuminate\Support\Facades\Session;
use Tests\Support\EventModuleTestCase;

class ServiceSlugRoutingTest extends EventModuleTestCase
{
    public function test_route_key_is_slug_and_generates_slug_urls(): void
    {
        $service = ChurchService::ensureDefault();
        $this->assertSame('servants-prep', $service->slug);
        $this->assertSame('slug', $service->getRouteKeyName());

        $url = route('services.apply', $service, absolute: false);
        $this->assertStringContainsString('/services/servants-prep/apply', $url);
        $this->assertStringNotContainsString('/services/'.$service->service_id.'/apply', $url);
    }

    public function test_numeric_service_url_permanently_redirects_to_slug(): void
    {
        $service = ChurchService::ensureDefault();
        $user = $this->createUser(['is_superadmin' => true]);

        $response = $this->actingAs($user)->get('/services/'.$service->service_id.'/apply');

        $response->assertStatus(301);
        $response->assertRedirect('/services/servants-prep/apply');
    }

    public function test_slug_hub_sets_service_context(): void
    {
        $service = ChurchService::ensureDefault();
        $user = $this->createUser(['is_superadmin' => true]);

        $response = $this->actingAs($user)->get('/s/servants-prep');

        $response->assertRedirect(route('hubs.service', absolute: false));
        $this->assertSame((int) $service->service_id, (int) Session::get(ServiceContextService::SESSION_KEY));
    }

    public function test_sync_from_route_accepts_slug_string(): void
    {
        $service = ChurchService::ensureDefault();
        $user = $this->createUser(['is_superadmin' => true]);

        app(ServiceContextService::class)->syncFromRoute($user, 'servants-prep');

        $this->assertSame((int) $service->service_id, (int) Session::get(ServiceContextService::SESSION_KEY));
    }

    public function test_new_service_auto_assigns_slug(): void
    {
        $service = $this->createService([
            'title' => 'Youth Care',
            'title_en' => 'Youth Care',
        ]);

        $this->assertNotEmpty($service->slug);
        $this->assertSame('youth-care', $service->slug);
    }
}
