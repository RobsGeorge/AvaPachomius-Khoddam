<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Support\PublicSite\ChurchPublicProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PublicProfileController extends Controller
{
    use ResolvesTenantChurch;

    public function edit()
    {
        $church = $this->resolveChurch();
        $profile = ChurchPublicProfile::fromSettings($church->settings);

        return view('church.public-profile.edit', [
            'church' => $church,
            'profile' => $profile,
        ]);
    }

    public function update(Request $request)
    {
        $church = $this->resolveChurch();

        $validated = $request->validate([
            'tagline.ar' => ['nullable', 'string', 'max:255'],
            'tagline.en' => ['nullable', 'string', 'max:255'],
            'about.ar' => ['nullable', 'string', 'max:5000'],
            'about.en' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'geo.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'geo.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'social.facebook' => ['nullable', 'url', 'max:500'],
            'social.youtube' => ['nullable', 'url', 'max:500'],
            'social.instagram' => ['nullable', 'url', 'max:500'],
            'liturgy_hours' => ['nullable', 'array', 'max:21'],
            'liturgy_hours.*.day' => ['nullable', 'string', 'max:80'],
            'liturgy_hours.*.time_ar' => ['nullable', 'string', 'max:120'],
            'liturgy_hours.*.time_en' => ['nullable', 'string', 'max:120'],
            'show_on_public_site.tagline' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'show_on_public_site.about' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'show_on_public_site.address' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'show_on_public_site.contact' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'show_on_public_site.social' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'show_on_public_site.liturgy_hours' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
        ]);

        $public = ChurchPublicProfile::normalizeInput($validated);
        $settings = is_array($church->settings) ? $church->settings : [];
        $settings[ChurchPublicProfile::SETTINGS_KEY] = $public;
        $church->settings = $settings;
        $church->save();

        AuditLogService::recordEvent('public_site.profile_updated', [
            'church_id' => $church->church_id,
            'actor_user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('church.public-profile.edit')
            ->with('success', __('public_site.profile_saved'));
    }
}
