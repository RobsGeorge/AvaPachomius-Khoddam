<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\ChurchMedia;
use App\Models\ChurchSiteSection;
use App\Services\PublicSite\ChurchSiteService;
use App\Support\PublicSite\ChurchBranding;
use App\Support\PublicSite\SectionTypes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * T10c — curated homepage editor (draft sections + publish).
 */
class HomepageEditorController extends Controller
{
    use ResolvesTenantChurch;

    public function __construct(private ChurchSiteService $siteService) {}

    public function edit()
    {
        $church = $this->resolveChurch();
        $site = $this->siteService->ensureSiteForChurch($church);
        $site->load('sections');

        return view('site.homepage.edit', [
            'church' => $church,
            'site' => $site,
            'sections' => $site->sections,
            'sectionTypes' => SectionTypes::all(),
            'media' => ChurchMedia::query()->orderByDesc('church_media_id')->get(),
            'branding' => ChurchBranding::fromSettings($church->settings),
        ]);
    }

    public function storeSection(Request $request)
    {
        $church = $this->resolveChurch();
        $site = $this->siteService->ensureSiteForChurch($church);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(SectionTypes::all())],
        ]);

        $this->siteService->createSection($site, $validated['type']);

        return redirect()
            ->route('site.homepage.edit')
            ->with('success', __('public_site.section_added'));
    }

    public function updateSection(Request $request, ChurchSiteSection $section)
    {
        $this->assertSectionBelongsToTenant($section);
        $input = $request->all();
        if ($section->type === SectionTypes::GALLERY && isset($input['media_ids']) && is_string($input['media_ids'])) {
            $input['media_ids'] = array_values(array_filter(array_map(
                'intval',
                preg_split('/[\s,]+/', $input['media_ids'], -1, PREG_SPLIT_NO_EMPTY)
            )));
        }
        $this->siteService->updateSection($section, $input);

        return redirect()
            ->route('site.homepage.edit')
            ->with('success', __('public_site.section_saved'));
    }

    public function destroySection(ChurchSiteSection $section)
    {
        $this->assertSectionBelongsToTenant($section);
        $this->siteService->deleteSection($section);

        return redirect()
            ->route('site.homepage.edit')
            ->with('success', __('public_site.section_deleted'));
    }

    public function reorder(Request $request)
    {
        $church = $this->resolveChurch();
        $site = $this->siteService->ensureSiteForChurch($church);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $this->siteService->reorderSections($site, $validated['order']);

        return redirect()
            ->route('site.homepage.edit')
            ->with('success', __('public_site.sections_reordered'));
    }

    public function preview()
    {
        $church = $this->resolveChurch();
        $site = $this->siteService->ensureSiteForChurch($church);
        $branding = ChurchBranding::fromSettings($church->settings);
        $sections = $this->siteService->draftSections($site);

        return view('public-site.home', [
            'church' => $church,
            'branding' => $branding,
            'sections' => $sections,
            'preview' => true,
        ]);
    }

    public function publish()
    {
        $church = $this->resolveChurch();
        $site = $this->siteService->ensureSiteForChurch($church);
        $this->siteService->publish($site);

        return redirect()
            ->route('site.homepage.edit')
            ->with('success', __('public_site.published'));
    }

    public function unpublish()
    {
        $church = $this->resolveChurch();
        $site = $this->siteService->ensureSiteForChurch($church);
        $this->siteService->unpublish($site);

        return redirect()
            ->route('site.homepage.edit')
            ->with('success', __('public_site.unpublished'));
    }

    private function assertSectionBelongsToTenant(ChurchSiteSection $section): void
    {
        $church = $this->resolveChurch();
        abort_unless((int) $section->church_id === (int) $church->church_id, 404);
    }
}
