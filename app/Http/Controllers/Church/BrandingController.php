<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Support\PublicSite\ChurchBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BrandingController extends Controller
{
    use ResolvesTenantChurch;

    public function edit()
    {
        $church = $this->resolveChurch();
        $branding = ChurchBranding::fromSettings($church->settings);

        return view('church.branding.edit', [
            'church' => $church,
            'branding' => $branding,
            'palettes' => ChurchBranding::PALETTES,
            'fonts' => ChurchBranding::FONTS,
            'logoUrl' => ChurchBranding::logoUrl($branding),
        ]);
    }

    public function update(Request $request)
    {
        $church = $this->resolveChurch();
        $current = ChurchBranding::fromSettings($church->settings);

        $validated = $request->validate([
            'palette' => ['required', 'string', Rule::in(array_merge(array_keys(ChurchBranding::PALETTES), ['custom']))],
            'primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'primary_text' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_display' => ['required', Rule::in(ChurchBranding::FONTS)],
            'font_body' => ['required', Rule::in(ChurchBranding::FONTS)],
            'apply_to_portal' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'clear_logo' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
        ]);

        $branding = ChurchBranding::normalizeInput(
            $validated + ['clear_logo' => $request->boolean('clear_logo')],
            $current['logo_path'] ?? null
        );

        if (! ChurchBranding::hasAcceptableContrast($branding['primary'], $branding['primary_text'])) {
            return back()
                ->withInput()
                ->withErrors(['primary_text' => __('public_site.branding_contrast_error')]);
        }

        if ($request->boolean('clear_logo') && ! empty($current['logo_path'])) {
            Storage::disk('public')->delete($current['logo_path']);
            $branding['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if (! empty($current['logo_path'])) {
                Storage::disk('public')->delete($current['logo_path']);
            }
            $branding['logo_path'] = $request->file('logo')->store(
                'church_logos/'.$church->church_id,
                'public'
            );
        }

        $settings = is_array($church->settings) ? $church->settings : [];
        $settings[ChurchBranding::SETTINGS_KEY] = $branding;
        $church->settings = $settings;
        $church->save();

        AuditLogService::recordEvent('public_site.branding_updated', [
            'church_id' => $church->church_id,
            'actor_user_id' => Auth::id(),
            'palette' => $branding['palette'],
        ]);

        return redirect()
            ->route('church.branding.edit')
            ->with('success', __('public_site.branding_saved'));
    }
}
