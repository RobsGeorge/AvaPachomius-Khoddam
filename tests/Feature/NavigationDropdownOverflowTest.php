<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Desktop nav dropdowns (Academic / Service / System / Superadmin) must stay
 * fully reachable: cap height at 70% of the viewport and scroll inside the panel
 * instead of running under the OS taskbar.
 */
class NavigationDropdownOverflowTest extends TestCase
{
    public function test_theme_css_caps_dropdown_panels_at_seventy_percent_viewport(): void
    {
        $css = (string) file_get_contents(public_path('css/khoddam-theme.css'));

        $this->assertMatchesRegularExpression(
            '/\.app-dropdown-panel\s*\{[^}]*max-height:\s*70vh;[^}]*max-height:\s*70dvh;/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.app-dropdown-panel\s*\{[^}]*max-height:\s*calc\(100(?:d)?vh/s',
            $css
        );
    }

    public function test_dropdown_scroll_helper_uses_shared_seventy_percent_cap(): void
    {
        $js = (string) file_get_contents(public_path('js/khoddam-ui.js'));

        $this->assertMatchesRegularExpression(
            '/function\s+initDropdownPanelScroll\s*\(/',
            $js
        );
        $this->assertMatchesRegularExpression(
            '/VIEWPORT_FRACTION\s*=\s*0\.7/',
            $js
        );
        $this->assertStringContainsString('visualViewport', $js);
        $this->assertDoesNotMatchRegularExpression(
            '/const padding = 12/',
            $js
        );
    }

    public function test_layout_cache_busts_dropdown_assets(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('khoddam-theme.css', $layout);
        $this->assertStringContainsString('khoddam-ui.js', $layout);
        $this->assertEquals(2, substr_count($layout, '?v=20260821-nav-dd'));
    }
}
