<?php

namespace App\Support\PublicSite;

/**
 * T10b — church.settings.branding (logo + palette; no church_site tables).
 */
final class ChurchBranding
{
    public const SETTINGS_KEY = 'branding';

    /** @var array<string, array{primary: string, accent: string, primary_text: string}> */
    public const PALETTES = [
        'khoddam' => [
            'primary' => '#7c3aed',
            'accent' => '#d4af37',
            'primary_text' => '#ffffff',
        ],
        'deaconia' => [
            'primary' => '#114b4f',
            'accent' => '#c9a227',
            'primary_text' => '#ffffff',
        ],
        'nile' => [
            'primary' => '#0b5f6b',
            'accent' => '#e0a106',
            'primary_text' => '#ffffff',
        ],
        'olive' => [
            'primary' => '#3f5d3a',
            'accent' => '#c4a35a',
            'primary_text' => '#ffffff',
        ],
        'desert' => [
            'primary' => '#8a4b2e',
            'accent' => '#d4a574',
            'primary_text' => '#ffffff',
        ],
    ];

    /** @var list<string> */
    public const FONTS = ['cairo', 'amiri', 'system-sans'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $base = self::PALETTES['khoddam'];

        return [
            'palette' => 'khoddam',
            'primary' => $base['primary'],
            'accent' => $base['accent'],
            'primary_text' => $base['primary_text'],
            'font_display' => 'cairo',
            'font_body' => 'cairo',
            'logo_path' => null,
            'apply_to_portal' => true,
        ];
    }

    /** @param  array<string, mixed>|null  $settings */
    public static function fromSettings(?array $settings): array
    {
        $branding = is_array($settings[self::SETTINGS_KEY] ?? null)
            ? $settings[self::SETTINGS_KEY]
            : [];

        return array_replace_recursive(self::defaults(), $branding);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeInput(array $input, ?string $existingLogoPath = null): array
    {
        $base = self::defaults();
        $paletteKey = (string) ($input['palette'] ?? 'khoddam');
        if (! array_key_exists($paletteKey, self::PALETTES) && $paletteKey !== 'custom') {
            $paletteKey = 'khoddam';
        }

        if ($paletteKey !== 'custom' && isset(self::PALETTES[$paletteKey])) {
            $colors = self::PALETTES[$paletteKey];
            $base['primary'] = $colors['primary'];
            $base['accent'] = $colors['accent'];
            $base['primary_text'] = $colors['primary_text'];
        } else {
            $base['primary'] = self::normalizeHex($input['primary'] ?? $base['primary']) ?? $base['primary'];
            $base['accent'] = self::normalizeHex($input['accent'] ?? $base['accent']) ?? $base['accent'];
            $base['primary_text'] = self::normalizeHex($input['primary_text'] ?? $base['primary_text']) ?? $base['primary_text'];
            $paletteKey = 'custom';
        }

        $base['palette'] = $paletteKey;
        $base['font_display'] = self::normalizeFont($input['font_display'] ?? 'cairo');
        $base['font_body'] = self::normalizeFont($input['font_body'] ?? 'cairo');
        $base['apply_to_portal'] = filter_var($input['apply_to_portal'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $base['logo_path'] = $existingLogoPath;

        if (! empty($input['clear_logo'])) {
            $base['logo_path'] = null;
        }

        return $base;
    }

    public static function normalizeHex(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return $value;
        }

        return null;
    }

    public static function normalizeFont(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, self::FONTS, true) ? $value : 'cairo';
    }

    /** WCAG-ish contrast ratio between two #RRGGBB colors. */
    public static function contrastRatio(string $hexA, string $hexB): float
    {
        $l1 = self::relativeLuminance($hexA);
        $l2 = self::relativeLuminance($hexB);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function hasAcceptableContrast(string $primary, string $primaryText): bool
    {
        return self::contrastRatio($primary, $primaryText) >= 3.0;
    }

    private static function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
        $mapped = array_map(static function (float $c): float {
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $mapped[0] + 0.7152 * $mapped[1] + 0.0722 * $mapped[2];
    }

    public static function fontStack(string $key): string
    {
        return match ($key) {
            'amiri' => '"Amiri", "Times New Roman", serif',
            'system-sans' => 'system-ui, -apple-system, "Segoe UI", sans-serif',
            default => '"Cairo", "Segoe UI", Tahoma, sans-serif',
        };
    }

    /** Public-site CSS variables (--ps-*). */
    public static function publicCss(array $branding): string
    {
        $primary = $branding['primary'];
        $accent = $branding['accent'];
        $primaryText = $branding['primary_text'];
        $display = self::fontStack($branding['font_display']);
        $body = self::fontStack($branding['font_body']);

        return implode(' ', [
            "--ps-primary: {$primary};",
            "--ps-primary-text: {$primaryText};",
            "--ps-accent: {$accent};",
            '--ps-bg: #f7faf9;',
            '--ps-surface: #ffffff;',
            '--ps-text: #1f2d2c;',
            '--ps-muted: #5b7773;',
            "--ps-font-display: {$display};",
            "--ps-font-body: {$body};",
            '--ps-radius: 14px;',
        ]);
    }

    /** Portal chrome CSS variables (align with course branding tokens). */
    public static function portalCss(array $branding): ?string
    {
        if (! ($branding['apply_to_portal'] ?? true)) {
            return null;
        }

        $primary = $branding['primary'];
        $accent = $branding['accent'];

        return ':root { '
            ."--color-primary: {$primary}; "
            ."--color-primary-hover: {$primary}; "
            ."--color-accent: {$accent}; "
           .'}';
    }

    public static function logoUrl(array $branding): ?string
    {
        $path = $branding['logo_path'] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return asset('storage/'.$path);
    }
}
