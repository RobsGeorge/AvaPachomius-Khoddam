<?php

namespace App\Support;

class FaviconComposer
{
    public function compose(string $iconFilePath): string
    {
        $raw = (string) file_get_contents($iconFilePath);

        if (! preg_match('/viewBox="([^"]+)"/', $raw, $viewBoxMatch)) {
            throw new \InvalidArgumentException('Icon SVG is missing a viewBox.');
        }

        $inner = preg_replace('/^.*?<svg[^>]*>/s', '', $raw);
        $inner = preg_replace('/<\/svg>\s*$/s', '', (string) $inner);
        $inner = preg_replace('/\sfill="currentColor"/', '', (string) $inner);

        [$viewX, $viewY, $viewWidth, $viewHeight] = array_map(
            'floatval',
            explode(' ', $viewBoxMatch[1])
        );

        $canvas = 64;
        $iconSize = (float) config('favicon.icon_size', 36);
        $scale = $iconSize / max($viewWidth, $viewHeight);
        $translateX = ($canvas / 2) - (($viewX + ($viewWidth / 2)) * $scale);
        $translateY = ($canvas / 2) - (($viewY + ($viewHeight / 2)) * $scale);

        $background = (string) config('favicon.background', '#0f4c5c');
        $foreground = (string) config('favicon.foreground', '#f7f3e9');
        $radius = (int) config('favicon.corner_radius', 12);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img">
  <rect width="64" height="64" rx="{$radius}" fill="{$background}"/>
  <g transform="translate({$translateX},{$translateY}) scale({$scale})" fill="{$foreground}">
    {$inner}
  </g>
</svg>
SVG;
    }
}
