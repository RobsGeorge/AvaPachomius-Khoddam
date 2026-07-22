<?php

namespace App\Support;

use App\Models\User;

class PageFavicon
{
    public function icon(?User $user = null, ?string $override = null): ?string
    {
        $override = $this->normalizeIcon($override);
        if ($override !== null) {
            return $override;
        }

        return NavigationHub::activePageIcon($user) ?? $this->routeFallbackIcon();
    }

    public function url(?User $user = null, ?string $override = null): string
    {
        $icon = $this->icon($user, $override);
        if ($icon === null) {
            return asset('favicon.svg');
        }

        $key = $this->iconKey($icon);
        if (! $this->iconExists($key)) {
            return asset('favicon.svg');
        }

        return route('favicon.show', ['icon' => $key]);
    }

    public function normalizeIcon(?string $icon): ?string
    {
        $icon = trim((string) $icon);
        if ($icon === '') {
            return null;
        }

        if (str_starts_with($icon, 'bi-')) {
            return $icon;
        }

        if (str_starts_with($icon, 'fas fa-')) {
            return $icon;
        }

        return null;
    }

    public function iconKey(string $icon): string
    {
        return str_replace(' ', '-', trim($icon));
    }

    public function iconPath(string $iconKey): string
    {
        return public_path('favicon/icons/'.$iconKey.'.svg');
    }

    public function iconExists(string $iconKey): bool
    {
        $path = $this->iconPath($iconKey);

        return is_file($path) && str_starts_with(realpath($path) ?: '', realpath(public_path('favicon/icons')) ?: '');
    }

    private function routeFallbackIcon(): ?string
    {
        foreach ((array) config('favicon.route_icons', []) as $pattern => $icon) {
            if (request()->routeIs($pattern)) {
                return $this->normalizeIcon($icon);
            }
        }

        return null;
    }
}
