<?php

namespace Tests\Feature\UseCases\Accessibility;

use Tests\TestCase;

/**
 * Guard: full-page navigations (login, logout, locale) must not re-run
 * entrance motion on every widget — that caused flicker + rise-from-bottom UX.
 *
 * Paths are resolved from this file (not public_path()) so worktree / vendor
 * junctions cannot accidentally assert against another checkout's assets.
 */
class PageRevealFlickerGuardTest extends TestCase
{
    public function test_widget_surfaces_do_not_auto_run_fade_in_up(): void
    {
        $css = $this->themeCss();

        foreach (['app-card', 'app-tile', 'hub-tile', 'hub-link-tile'] as $class) {
            $this->assertDoesNotMatchRegularExpression(
                '/\.'.preg_quote($class, '/').'\b[^{]*\{[^}]*\banimation\s*:\s*fadeInUp\b/s',
                $css,
                ".{$class} must not auto-run fadeInUp on every page load (login/logout/locale flicker)."
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/\.(?:animate-in|app-card|app-tile|hub-tile|hub-link-tile)\s*,[^\{]*\{[^}]*\banimation\s*:\s*fadeInUp\b/s',
            $css,
            'Grouped widget selectors must not share a fadeInUp entrance animation.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\.(?:accordion-item|roles-hub-panel)\b[^{]*\{[^}]*\banimation\s*:\s*khoddamFadeIn\b/s',
            $css,
            'Accordion/roles panels must not auto-run khoddamFadeIn on every page load.'
        );
    }

    public function test_init_reveal_does_not_mutate_animation_delay_after_paint(): void
    {
        $js = $this->themeJs();

        $this->assertDoesNotMatchRegularExpression(
            '/\bstyle\.animationDelay\s*=/',
            $js,
            'Mutating animationDelay after CSS animation start restarts reveals and causes flicker.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/initReveal\s*\(\s*\)\s*;/',
            $js,
            'initReveal must not run on DOMContentLoaded (entrance stagger was the flicker amplifier).'
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
