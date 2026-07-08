<?php

namespace App\Http\Controllers;

use App\Models\PortalSettings;
use App\Models\User;
use App\Services\PortalThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class SuperAdminThemeController extends Controller
{
    public function __construct(
        private PortalThemeService $themes
    ) {}

    public function index()
    {
        $settings = PortalSettings::current();
        $publishedBy = null;

        if ($settings->theme_colors_published_by_user_id) {
            $publishedBy = User::query()->find($settings->theme_colors_published_by_user_id);
        }

        return view('superadmin.theme', [
            'defaults' => $this->themes->defaults(),
            'draft' => $this->themes->draftPalette(),
            'published' => $this->themes->publishedPalette(),
            'publishedAt' => $settings->theme_colors_published_at,
            'publishedBy' => $publishedBy,
            'hasDraft' => is_array($settings->theme_colors_draft),
        ]);
    }

    public function saveDraft(Request $request)
    {
        $palette = $this->validatedPalette($request);

        try {
            $this->themes->saveDraft($palette);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['theme' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('superadmin.theme.index')
            ->with('success', __('pages.theme_colors_draft_saved'));
    }

    public function publish(Request $request)
    {
        $palette = $this->validatedPalette($request);

        try {
            $this->themes->publish($palette);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['theme' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('superadmin.theme.index')
            ->with('success', __('pages.theme_colors_published'));
    }

    public function discardDraft()
    {
        $this->themes->discardDraft();

        return redirect()
            ->route('superadmin.theme.index')
            ->with('success', __('pages.theme_colors_draft_discarded'));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function validatedPalette(Request $request): array
    {
        $colorRule = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return $request->validate([
            'light' => ['required', 'array'],
            'light.primary' => $colorRule,
            'light.primary_hover' => $colorRule,
            'light.title' => $colorRule,
            'light.link' => $colorRule,
            'dark' => ['required', 'array'],
            'dark.primary' => $colorRule,
            'dark.primary_hover' => $colorRule,
            'dark.title' => $colorRule,
            'dark.link' => $colorRule,
        ]);
    }
}
