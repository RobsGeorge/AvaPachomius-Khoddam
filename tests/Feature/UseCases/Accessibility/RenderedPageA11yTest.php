<?php

namespace Tests\Feature\UseCases\Accessibility;

use DOMDocument;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\EventModuleTestCase;

/**
 * Server-checkable accessibility invariants across every rendered page (TC-A11Y-*).
 * These are the WCAG checks possible without a browser; keyboard/focus/contrast/screen-reader
 * behaviours are covered by docs/product/accessibility-audit.md (manual).
 *
 * Rendered as a SuperAdmin so the sweep reaches the widest set of pages.
 */
class RenderedPageA11yTest extends EventModuleTestCase
{
    public function test_every_page_declares_language_direction_and_title(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'a11y-super@example.com']);
        $this->actingAs($admin);

        $failures = [];
        foreach ($this->pages() as $route) {
            $response = $this->get('/'.ltrim($route->uri(), '/'));
            // Only full rendered pages carry a layout; redirects (302) have a bare body,
            // and file/JSON downloads (e.g. data export) are not HTML pages at all.
            if ($response->getStatusCode() !== 200) {
                continue;
            }
            if (! str_contains(strtolower((string) $response->headers->get('content-type')), 'text/html')) {
                continue;
            }
            $html = $response->getContent();
            if (! is_string($html) || $html === '') {
                continue;
            }

            $name = $route->getName() ?? $route->uri();

            if (! preg_match('/<html[^>]*\blang=("|\')[a-zA-Z-]+\1/', $html)) {
                $failures[] = "$name: <html> missing lang";
            }
            if (! preg_match('/<html[^>]*\bdir=("|\')(rtl|ltr)\1/', $html)) {
                $failures[] = "$name: <html> missing dir";
            }
            if (! preg_match('/<title>\s*\S.*<\/title>/s', $html)) {
                $failures[] = "$name: empty or missing <title>";
            }
        }

        $this->assertSame([], $failures, "Pages missing lang/dir/title:\n".implode("\n", $failures));
    }

    public function test_all_images_declare_alt_text(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'a11y-img@example.com']);
        $this->actingAs($admin);

        $violations = [];
        foreach ($this->pages() as $route) {
            $html = $this->get('/'.ltrim($route->uri(), '/'))->getContent();
            if (! is_string($html) || stripos($html, '<img') === false) {
                continue;
            }

            foreach ($this->imgElements($html) as $img) {
                // alt="" is valid (decorative); we only require the attribute to be present.
                if (! $img->hasAttribute('alt')) {
                    $src = $img->getAttribute('src');
                    $violations[] = ($route->getName() ?? $route->uri()).": <img> without alt (src=".mb_substr($src, 0, 40).')';
                }
            }
        }

        $this->assertSame([], $violations, "Images missing an alt attribute:\n".implode("\n", $violations));
    }

    public function test_pages_expose_a_skip_to_content_link(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'a11y-skip@example.com']);

        $html = $this->actingAs($admin)->get(route('superadmin.index'))->getContent();

        // A11Y-01: a skip link that targets the main landmark, and a focusable <main id>.
        $this->assertMatchesRegularExpression(
            '/<a[^>]*\bhref=("|\')#app-main\1[^>]*>/',
            (string) $html,
            'Skip-to-content link (href="#app-main") is missing'
        );
        $this->assertMatchesRegularExpression(
            '/<main[^>]*\bid=("|\')app-main\1/',
            (string) $html,
            '<main id="app-main"> landmark target is missing'
        );
    }

    public function test_pages_expose_a_polite_live_region(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'a11y-live@example.com']);

        $html = (string) $this->actingAs($admin)->get(route('superadmin.index'))->getContent();

        // A11Y-03: a polite ARIA live region for announcing transient messages.
        $this->assertMatchesRegularExpression(
            '/\baria-live=("|\')polite\1/',
            $html,
            'Polite aria-live region is missing from the layout'
        );
    }

    public function test_theme_css_honors_reduced_motion(): void
    {
        // A11Y-02: CSS respects prefers-reduced-motion (checked at the source, not per page).
        $css = (string) file_get_contents(public_path('css/khoddam-theme.css'));

        $this->assertStringContainsString(
            'prefers-reduced-motion',
            $css,
            'Theme CSS does not honor prefers-reduced-motion'
        );
    }

    /** @return list<\DOMElement> */
    private function imgElements(string $html): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $imgs = [];
        foreach ($doc->getElementsByTagName('img') as $img) {
            $imgs[] = $img;
        }

        return $imgs;
    }

    /** @return list<RoutingRoute> Parameterless authenticated web GET pages. */
    private function pages(): array
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if (! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
                continue;
            }
            $mw = $route->gatherMiddleware();
            if (! in_array('web', $mw, true)) {
                continue;
            }
            // Only pages that render a full layout (skip pure redirects like logout).
            if (in_array($route->getName(), ['logout', 'locale.switch'], true)) {
                continue;
            }
            $routes[] = $route;
        }

        return $routes;
    }
}
