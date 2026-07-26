<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\PublicSite\ChurchBranding;
use App\Support\PublicSite\ChurchPublicProfile;
use App\Tenancy\TenantContext;
use App\Models\Church;

/**
 * T10a — guest-readable church public details (not homepage CMS).
 * T10b — branding CSS/logo applied on the public page.
 */
class ChurchPublicProfileController extends Controller
{
    public function show()
    {
        $church = TenantContext::current() ?? Church::main();
        abort_unless($church, 404);

        // When tenancy is on, require the public_site capability; dormant mode keeps Tenant Zero open.
        if (config('tenancy.enabled') && ! $church->hasCapability('public_site')) {
            abort(404);
        }

        $profile = ChurchPublicProfile::fromSettings($church->settings);
        $branding = ChurchBranding::fromSettings($church->settings);

        return view('public-site.profile', [
            'church' => $church,
            'profile' => $profile,
            'branding' => $branding,
        ]);
    }
}
