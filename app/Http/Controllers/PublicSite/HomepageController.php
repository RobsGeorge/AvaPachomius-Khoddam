<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchSite;
use App\Services\PublicSite\ChurchSiteService;
use App\Support\PublicSite\ChurchBranding;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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

        // Read-only: never create a site row on a public GET /.
        if (! Schema::hasTable('church_site')) {
            return $this->fallbackHome();
        }

        $site = ChurchSite::query()->where('church_id', $church->church_id)->first();
        if (! $site || ! $site->isPublished()) {
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
