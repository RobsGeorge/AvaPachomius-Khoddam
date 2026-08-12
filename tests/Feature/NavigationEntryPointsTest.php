<?php

namespace Tests\Feature;

use App\Support\NavigationHub;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class NavigationEntryPointsTest extends EventModuleTestCase
{
    public function test_grouped_sections_preserve_order_and_drop_empty(): void
    {
        $links = [
            ['url' => '/a', 'label' => 'A', 'category' => 'assessment', 'icon' => 'bi-a', 'active' => false],
            ['url' => '/b', 'label' => 'B', 'category' => 'learning', 'icon' => 'bi-b', 'active' => false],
            ['url' => '/c', 'label' => 'C', 'category' => 'learning', 'icon' => 'bi-c', 'active' => false],
        ];

        $sections = NavigationHub::groupedSections($links, [
            'learning' => 'Learning',
            'assessment' => 'Assessment',
            'people' => 'People',
        ]);

        $this->assertSame(['learning', 'assessment'], array_column($sections, 'key'));
        $this->assertCount(2, $sections[0]['links']);
        $this->assertCount(1, $sections[1]['links']);
    }

    public function test_academic_links_carry_hub_categories(): void
    {
        $user = $this->createUser([
            'email' => 'nav-academic-sections@example.com',
            'is_superadmin' => true,
        ]);

        $links = NavigationHub::academicLinks($user);
        $this->assertNotEmpty($links);

        foreach ($links as $link) {
            $this->assertArrayHasKey('category', $link, 'Academic link missing category: '.$link['label']);
            $this->assertContains($link['category'], ['learning', 'assessment', 'people', 'community']);
        }

        $this->actingAs($user)
            ->get(route('hubs.academic'))
            ->assertOk()
            ->assertSee(__('nav.hub_section_learning'), false)
            ->assertSee(__('nav.hub_section_community'), false);
    }

    public function test_service_hub_renders_all_non_empty_section_headers(): void
    {
        if (! Schema::hasTable('service') || ! Schema::hasTable('user_service_role')) {
            $this->markTestSkipped('Service schema not ready.');
        }

        $service = $this->createService(['title' => 'Nav Section Service']);
        $user = $this->createUser([
            'email' => 'nav-service-sections@example.com',
            'is_superadmin' => true,
        ]);
        $this->assignServiceRole($user, $service, asPrimary: true);

        $links = NavigationHub::serviceLinks($user);
        $this->assertNotEmpty($links);

        $sections = NavigationHub::groupedSections($links, NavigationHub::serviceSectionDefinitions());
        $this->assertNotEmpty($sections);

        $response = $this->actingAs($user)->get(route('hubs.service'))->assertOk();

        foreach ($sections as $section) {
            $response->assertSee($section['title'], false);
            foreach ($section['links'] as $link) {
                $response->assertSee($link['label'], false);
            }
        }

        // Pastoral / finance / public_site must not be silently dropped when present.
        $churchCats = collect($links)->pluck('category')->unique()->intersect(['pastoral', 'finance', 'public_site']);
        foreach ($churchCats as $cat) {
            $this->assertTrue(
                collect($sections)->contains('key', $cat),
                "Service hub dropped category [{$cat}] that NavigationHub emitted."
            );
        }
    }

    public function test_superadmin_courses_tile_not_active_on_admin_services(): void
    {
        if (! Schema::hasTable('service')) {
            $this->markTestSkipped('Service schema not ready.');
        }

        $user = $this->createUser([
            'email' => 'nav-sa-active@example.com',
            'is_superadmin' => true,
        ]);

        $this->actingAs($user)->get(route('admin.services.index'))->assertOk();

        $coursesLink = collect(NavigationHub::superadminLinks($user->fresh()))
            ->first(fn ($link) => ($link['label'] ?? '') === __('pages.manage_services_and_courses'));

        $this->assertNotNull($coursesLink);
        $this->assertFalse((bool) ($coursesLink['active'] ?? false));
    }
}
