<?php

namespace Tests\Feature\UseCases\Accessibility;

use Tests\TestCase;

/**
 * Guard: entrance motion is allowed only under body.js-page-reveal, and only
 * when navigating to a new path. Same-URL reloads must stay static (no flicker).
 *
 * Paths are resolved from this file (not public_path()) so worktree / vendor
 * junctions cannot accidentally assert against another checkout's assets.
 */
class PageRevealFlickerGuardTest extends TestCase
{
    public function test_widget_surfaces_do_not_auto_run_ungated_fade_in_up(): void
    {
        $css = $this->themeCss();

        preg_match_all(
            '/^[^{]*\{[^}]*\banimation\s*:\s*fadeInUp\b[^}]*\}/ms',
            $css,
            $fadeBlocks
        );
        $this->assertNotEmpty($fadeBlocks[0], 'Expected at least one gated fadeInUp rule.');
        foreach ($fadeBlocks[0] as $block) {
            $this->assertStringContainsString(
                'js-page-reveal',
                $block,
                'fadeInUp must only appear under body.js-page-reveal (ungated widgets flicker on reload).'
            );
        }

        preg_match_all(
            '/^[^{]*\{[^}]*\banimation\s*:\s*khoddamFadeIn\b[^}]*\}/ms',
            $css,
            $fadeBlocks
        );
        $this->assertNotEmpty($fadeBlocks[0], 'Expected at least one gated khoddamFadeIn rule.');
        foreach ($fadeBlocks[0] as $block) {
            $this->assertStringContainsString(
                'js-page-reveal',
                $block,
                'khoddamFadeIn must only appear under body.js-page-reveal.'
            );
        }
    }

    public function test_entrance_motion_is_gated_behind_js_page_reveal(): void
    {
        $css = $this->themeCss();

        foreach (['app-card', 'app-tile', 'hub-tile', 'hub-link-tile', 'animate-in'] as $class) {
            $this->assertMatchesRegularExpression(
                '/body\.js-page-reveal\s+\.'.preg_quote($class, '/').'\b/',
                $css,
                "Expected body.js-page-reveal .{$class} gate for entrance motion."
            );
        }

        $this->assertMatchesRegularExpression(
            '/body\.js-page-reveal\s+\.accordion-item\b/',
            $css,
            'Expected body.js-page-reveal .accordion-item gate.'
        );
        $this->assertMatchesRegularExpression(
            '/body\.js-page-reveal\s+\.roles-hub-panel\b/',
            $css,
            'Expected body.js-page-reveal .roles-hub-panel gate.'
        );
    }

    public function test_init_reveal_is_path_keyed_and_orders_delays_before_class(): void
    {
        $js = $this->themeJs();

        $this->assertMatchesRegularExpression(
            '/initReveal\s*\(\s*\)\s*;/',
            $js,
            'initReveal must run on DOMContentLoaded for new-page reveals.'
        );

        $this->assertStringContainsString(
            'khoddam.revealPath',
            $js,
            'initReveal must key reveals by sessionStorage path.'
        );

        $this->assertMatchesRegularExpression(
            '/last\s*===\s*path|path\s*===\s*last/',
            $js,
            'initReveal must skip animation when the path matches the last visit.'
        );

        if (! preg_match('/function\s+initReveal\s*\(\s*\)\s*\{(?P<body>.*)\n    function\s+\w+/s', $js, $m)) {
            $this->fail('Could not locate initReveal function body.');
        }

        $body = $m['body'];
        $delayPos = strpos($body, 'animationDelay');
        $classPos = strpos($body, 'js-page-reveal');

        $this->assertNotFalse($delayPos, 'initReveal must set animationDelay for stagger.');
        $this->assertNotFalse($classPos, 'initReveal must add js-page-reveal to start gated CSS.');
        $this->assertLessThan(
            $classPos,
            $delayPos,
            'animationDelay must be assigned before adding js-page-reveal (avoids restart flicker).'
        );
    }

    public function test_theme_css_still_honors_reduced_motion(): void
    {
        $this->assertStringContainsString(
            'prefers-reduced-motion',
            $this->themeCss(),
            'Theme CSS must keep prefers-reduced-motion for accessibility.'
        );
    }

    private function themeCss(): string
    {
        $path = $this->repoRoot().DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'khoddam-theme.css';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function themeJs(): string
    {
        $path = $this->repoRoot().DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'khoddam-ui.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function repoRoot(): string
    {
        // tests/Feature/UseCases/Accessibility → repo root
        return dirname(__DIR__, 4);
    }
}
