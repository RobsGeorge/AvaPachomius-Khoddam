<?php

namespace App\Http\Controllers;

use App\Services\PortalThemeService;
use Symfony\Component\HttpFoundation\Response;

class PortalThemeCssController extends Controller
{
    public function __construct(
        private PortalThemeService $themes
    ) {}

    public function show(): Response
    {
        return response(
            $this->themes->publishedCssBlock(),
            200,
            [
                'Content-Type' => 'text/css; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }
}
