<?php

namespace App\Services;

use App\Models\PortalSettings;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class PortalThemeService
{
    public const MODE_LIGHT = 'light';

    public const MODE_DARK = 'dark';

  /** @var array<string, array<string, string>> */
    public const DEFAULTS = [
        self::MODE_LIGHT => [
            'primary' => '#7c3aed',
            'primary_hover' => '#6d28d9',
            'title' => '#5b21b6',
            'link' => '#6d28d9',
        ],
        self::MODE_DARK => [
            'primary' => '#d4af37',
            'primary_hover' => '#c9a227',
            'title' => '#d4af37',
            'link' => '#f0d875',
        ],
    ];

    /** @var list<string> */
    public const COLOR_KEYS = ['primary', 'primary_hover', 'title', 'link'];

    public function defaults(): array
    {
        return self::DEFAULTS;
    }

    public function publishedPalette(): array
    {
        $settings = PortalSettings::current();
        $stored = $settings->theme_colors_published;

        if (! is_array($stored)) {
            return self::DEFAULTS;
        }

        return $this->mergePalettes(self::DEFAULTS, $stored);
    }

    public function draftPalette(): array
    {
        $settings = PortalSettings::current();
        $stored = $settings->theme_colors_draft;

        if (! is_array($stored)) {
            return $this->publishedPalette();
        }

        return $this->mergePalettes(self::DEFAULTS, $stored);
    }

    /**
     * @param  array<string, array<string, string>>  $input
     * @return array<string, array<string, string>>
     */
    public function validatePalette(array $input): array
    {
        $validated = [];

        foreach ([self::MODE_LIGHT, self::MODE_DARK] as $mode) {
            $validated[$mode] = [];
            foreach (self::COLOR_KEYS as $key) {
                $value = $input[$mode][$key] ?? self::DEFAULTS[$mode][$key];
                if (! is_string($value) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    throw new \InvalidArgumentException("Invalid color for {$mode}.{$key}");
                }
                $validated[$mode][$key] = strtolower($value);
            }
        }

        return $validated;
    }

    /**
     * @param  array<string, array<string, string>>  $palette
     */
    public function saveDraft(array $palette): PortalSettings
    {
        $settings = PortalSettings::current();
        $settings->theme_colors_draft = $this->validatePalette($palette);
        $settings->save();

        return $settings->refresh();
    }

  /**
   * @param  array<string, array<string, string>>  $palette
   */
    public function publish(array $palette): PortalSettings
    {
        $validated = $this->validatePalette($palette);
        $settings = PortalSettings::current();
        $settings->theme_colors_draft = $validated;
        $settings->theme_colors_published = $validated;
        $settings->theme_colors_published_at = now();
        $settings->theme_colors_published_by_user_id = Auth::id();
        $settings->save();

        return $settings->refresh();
    }

    public function discardDraft(): PortalSettings
    {
        $settings = PortalSettings::current();
        $settings->theme_colors_draft = null;
        $settings->save();

        return $settings->refresh();
    }

    public function publishedCssBlock(): string
    {
        return $this->buildCssBlock($this->publishedPalette());
    }

    /**
     * @param  array<string, array<string, string>>  $palette
     */
    public function buildCssBlock(array $palette): string
    {
        $lines = [];

        foreach ([self::MODE_LIGHT => 'body.theme-light', self::MODE_DARK => 'body.theme-dark'] as $mode => $selector) {
            $colors = $palette[$mode] ?? self::DEFAULTS[$mode];
            $primary = $colors['primary'];
            $primaryHover = $colors['primary_hover'];
            $title = $colors['title'];
            $link = $colors['link'];

            $lines[] = "{$selector} {";
            $lines[] = "    --color-primary: {$primary};";
            $lines[] = "    --color-primary-hover: {$primaryHover};";
            $lines[] = "    --color-title: {$title};";
            $lines[] = "    --color-title-accent: {$primary};";
            $lines[] = "    --color-link: {$link};";
            $lines[] = "    --color-link-hover: {$primaryHover};";
            $lines[] = "    --color-nav-active: {$primary};";
            $lines[] = '}';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, array<string, string>>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, array<string, string>>
     */
    private function mergePalettes(array $base, array $overrides): array
    {
        $merged = $base;

        foreach ([self::MODE_LIGHT, self::MODE_DARK] as $mode) {
            if (! isset($overrides[$mode]) || ! is_array($overrides[$mode])) {
                continue;
            }

            foreach (self::COLOR_KEYS as $key) {
                $value = $overrides[$mode][$key] ?? null;
                if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    $merged[$mode][$key] = strtolower($value);
                }
            }
        }

        return $merged;
    }
}
