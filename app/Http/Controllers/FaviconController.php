<?php

namespace App\Http\Controllers;

use App\Support\FaviconComposer;
use App\Support\PageFavicon;
use Illuminate\Http\Response;

class FaviconController extends Controller
{
    public function show(string $icon, PageFavicon $pageFavicon, FaviconComposer $composer): Response
    {
        if (! $pageFavicon->iconExists($icon)) {
            abort(404);
        }

        $svg = $composer->compose($pageFavicon->iconPath($icon));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
