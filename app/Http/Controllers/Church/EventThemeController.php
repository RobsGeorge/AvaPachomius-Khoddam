<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Support\EventTheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventThemeController extends Controller
{
    use ResolvesTenantChurch;

    public function edit()
    {
        $church = $this->resolveChurch();
        $config = EventTheme::fromSettings($church->settings);

        return view('church.event-theme.edit', [
            'church' => $church,
            'config' => $config,
            'active' => EventTheme::isActive($config),
        ]);
    }

    public function update(Request $request)
    {
        $church = $this->resolveChurch();

        $validated = $request->validate([
            'enabled_manual' => ['nullable', 'boolean'],
            'periods' => ['nullable', 'array', 'max:'.EventTheme::MAX_PERIODS],
            'periods.*.start' => ['nullable', 'date_format:Y-m-d', 'required_with:periods.*.end'],
            'periods.*.end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:periods.*.start'],
            'periods.*.label' => ['nullable', 'string', 'max:80'],
        ]);

        $config = EventTheme::normalizeInput([
            'enabled_manual' => $request->boolean('enabled_manual'),
            'periods' => $validated['periods'] ?? [],
        ]);

        $settings = is_array($church->settings) ? $church->settings : [];
        $settings[EventTheme::SETTINGS_KEY] = $config;
        $church->settings = $settings;
        $church->save();

        AuditLogService::recordEvent('event_theme.updated', [
            'church_id' => $church->church_id,
            'actor_user_id' => Auth::id(),
            'enabled_manual' => $config['enabled_manual'],
            'periods' => count($config['periods']),
        ]);

        return redirect()
            ->route('church.event-theme.edit')
            ->with('success', __('event_theme.saved'));
    }
}
