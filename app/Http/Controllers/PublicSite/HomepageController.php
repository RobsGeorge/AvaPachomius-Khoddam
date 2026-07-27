<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\PublicSite\ChurchSiteService;
use App\Support\PublicSite\ChurchBranding;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;

/**
 * T10c — guest homepage at GET / when published.
 */
class HomepageController extends Controller
{
    public function __construct(private ChurchSiteService $siteService) {}

    public function show()
    {
        $church = TenantContext::current() ?? Church::main();
        abort_unless($church, 404);

        if (config('tenancy.enabled') && ! $church->hasCapability('public_site')) {
            return $this->fallbackHome();
        }

        $site = $this->siteService->ensureSiteForChurch($church);

        if (! $site->isPublished()) {
            return $this->fallbackHome();
        }

        $branding = ChurchBranding::fromSettings($church->settings);
        $sections = $this->siteService->publishedSections($site);

        return view('public-site.home', [
            'church' => $church,
            'branding' => $branding,
            'sections' => $sections,
            'preview' => false,
        ]);
    }

    private function fallbackHome()
    {
        if (Auth::check()) {
            return redirect()->route('courses.select');
        }

        return redirect()->route('login');
    }
}
