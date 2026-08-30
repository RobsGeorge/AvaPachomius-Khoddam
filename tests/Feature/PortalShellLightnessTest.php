<?php

namespace Tests\Feature;

use Tests\Support\EventModuleTestCase;

/**
 * Guards the authenticated portal shell against regressing to heavy global assets
 * that make every mobile navigation feel slow (full document reload + CDN tax).
 */
class PortalShellLightnessTest extends EventModuleTestCase
{
    public function test_dashboard_shell_omits_heavy_global_cdns(): void
    {
        $user = $this->createUser([
            'email' => 'shell-light@example.com',
            'is_superadmin' => true,
        ]);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('font-awesome', $html);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com/ajax/libs/font-awesome', $html);
        $this->assertStringNotContainsString('animate.css', $html);
        $this->assertStringNotContainsString('animate.min.css', $html);
        $this->assertStringNotContainsString('family=Cairo:wght@400;600;700;800', $html);

        $this->assertStringContainsString('family=Cairo:wght@400;700', $html);
        $this->assertStringContainsString('rel="preconnect"', $html);
        $this->assertStringContainsString('bootstrap-icons', $html);
        $this->assertStringContainsString('id="kh-page-loader"', $html);
        $this->assertStringContainsString('khoddam-theme.css', $html);
    }

    public function test_dashboard_does_not_render_font_awesome_icon_classes(): void
    {
        $user = $this->createUser([
            'email' => 'shell-icons@example.com',
            'is_superadmin' => true,
        ]);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/\bclass="[^"]*\bfas\s+fa-/', $html);
        $this->assertDoesNotMatchRegularExpression("/\bclass='[^']*\\bfas\\s+fa-/", $html);
    }

    public function test_theme_css_uses_fast_reveal_and_no_loader_blur(): void
    {
        $css = file_get_contents(public_path('css/khoddam-theme.css'));
        $this->assertNotFalse($css);

        $this->assertMatchesRegularExpression('/--reveal-duration:\s*0\.4s/', $css);
        $this->assertStringContainsString('@keyframes kh-nav-progress', $css);
        $this->assertStringContainsString('khoddam-swal-animate', $css);
        // Full-screen navigation blur was removed; keep card blurs untouched.
        $this->assertDoesNotMatchRegularExpression(
            '/\.kh-page-loader\s*\{[^}]*backdrop-filter:\s*blur/s',
            $css
        );
    }

    public function test_student_photo_partial_is_lazy_loaded(): void
    {
        $partial = file_get_contents(resource_path('views/students/partials/student-photo.blade.php'));
        $this->assertNotFalse($partial);
        $this->assertStringContainsString('loading="lazy"', $partial);
        $this->assertStringContainsString('decoding="async"', $partial);
        $this->assertStringContainsString('width="56"', $partial);
        $this->assertStringContainsString('height="56"', $partial);
    }

    public function test_assignment_show_does_not_load_select2_or_jquery(): void
    {
        $view = file_get_contents(resource_path('views/assignments/show.blade.php'));
        $this->assertNotFalse($view);
        $this->assertStringNotContainsString('select2', $view);
        $this->assertStringNotContainsString('jquery', strtolower($view));
    }
}
