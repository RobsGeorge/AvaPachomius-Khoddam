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

    public function test_get_route_key_falls_back_to_id_when_slug_is_blank(): void
    {
        $service = $this->createService([
            'title' => 'Unslugged Legacy',
            'title_en' => 'Unslugged Legacy',
        ]);
        $service->forceFill(['slug' => null])->saveQuietly();

        $this->assertSame('slug', $service->getRouteKeyName());
        $this->assertSame((string) $service->service_id, (string) $service->getRouteKey());

        $url = route('admin.services.edit', $service, absolute: false);
        $this->assertStringContainsString('/admin/services/'.$service->service_id.'/edit', $url);

        $user = $this->createUser(['is_superadmin' => true, 'email' => 'svc-slug-fallback@example.com']);
        $this->actingAs($user)
            ->get($url)
            ->assertOk();
    }

    public function test_backfill_assigns_unique_slugs_to_legacy_services(): void
    {
        $first = $this->createService(['title' => 'Alpha Team', 'title_en' => 'Alpha Team']);
        $second = $this->createService(['title' => 'Alpha Team', 'title_en' => 'Alpha Team']);
        $first->forceFill(['slug' => null])->saveQuietly();
        $second->forceFill(['slug' => null])->saveQuietly();

        $updated = ChurchService::backfillMissingSlugs();

        $this->assertSame(2, $updated);
        $this->assertNotEmpty($first->fresh()->slug);
        $this->assertNotEmpty($second->fresh()->slug);
        $this->assertNotSame($first->fresh()->slug, $second->fresh()->slug);
    }
}
