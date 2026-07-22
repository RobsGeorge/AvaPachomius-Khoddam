<?php

namespace Tests\Feature;

use App\Services\CourseContextService;
use App\Support\FaviconComposer;
use App\Support\PageFavicon;
use Tests\Support\EventModuleTestCase;

class PageFaviconTest extends EventModuleTestCase
{
    public function test_guest_pages_use_default_cross_favicon(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="'.asset('favicon.svg').'"', false);
    }

    public function test_assignments_page_uses_nav_icon_favicon(): void
    {
        $course = $this->createCourse();
        $role = $this->courseRoleWithPermissions($course, 'favicon-assign', ['assignment.view']);
        $user = $this->createUser(['email' => 'favicon-assign@example.com']);
        $this->assignCourseRole($user, $course, $role);
        app(CourseContextService::class)->setCurrentCourse($user, $course->course_id);

        $expected = route('favicon.show', ['icon' => 'bi-journal-text']);

        $this->actingAs($user)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('href="'.$expected.'"', false);
    }

    public function test_notifications_page_uses_entry_icon_favicon(): void
    {
        $user = $this->createUser(['email' => 'favicon-notify@example.com']);

        $expected = route('favicon.show', ['icon' => 'bi-bell']);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('href="'.$expected.'"', false);
    }

    public function test_favicon_route_renders_branded_svg(): void
    {
        $response = $this->get(route('favicon.show', ['icon' => 'bi-gear']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $this->assertStringContainsString('#0f4c5c', (string) $response->getContent());
        $this->assertStringContainsString('<path', (string) $response->getContent());
    }

    public function test_unknown_favicon_icon_returns_not_found(): void
    {
        $this->get(route('favicon.show', ['icon' => 'bi-does-not-exist']))->assertNotFound();
    }

    public function test_page_favicon_falls_back_to_cross_when_icon_asset_missing(): void
    {
        $favicon = app(PageFavicon::class);

        $this->assertSame(
            asset('favicon.svg'),
            $favicon->url(null, 'bi-missing-icon')
        );
    }

    public function test_favicon_composer_wraps_bootstrap_icon_paths(): void
    {
        $svg = app(FaviconComposer::class)->compose(public_path('favicon/icons/bi-bell.svg'));

        $this->assertStringContainsString('viewBox="0 0 64 64"', $svg);
        $this->assertStringContainsString('<rect width="64" height="64"', $svg);
        $this->assertStringContainsString('<path', $svg);
    }
}
