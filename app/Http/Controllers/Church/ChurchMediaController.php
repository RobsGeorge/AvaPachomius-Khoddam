<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\ChurchMedia;
use App\Services\PublicSite\ChurchSiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * T10c — homepage media uploads (church/{id}/site/...).
 */
class ChurchMediaController extends Controller
{
    use ResolvesTenantChurch;

    public function __construct(private ChurchSiteService $siteService) {}

    public function store(Request $request)
    {
        $church = $this->resolveChurch();

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt_ar' => ['nullable', 'string', 'max:255'],
            'alt_en' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store('church/'.$church->church_id.'/site', 'public');
        $size = @getimagesize($file->getRealPath());

        ChurchMedia::create([
            'church_id' => $church->church_id,
            'path' => $path,
            'alt_ar' => $validated['alt_ar'] ?? null,
            'alt_en' => $validated['alt_en'] ?? null,
            'width' => is_array($size) ? ($size[0] ?? null) : null,
            'height' => is_array($size) ? ($size[1] ?? null) : null,
        ]);

        return redirect()
            ->route('site.homepage.edit')
            ->with('success', __('public_site.media_uploaded'));
    }

    public function destroy(ChurchMedia $media)
    {
        $church = $this->resolveChurch();
        abort_unless((int) $media->church_id === (int) $church->church_id, 404);

        $this->siteService->deleteMedia($media);

        return redirect()
            ->route('site.homepage.edit')
            ->with('success', __('public_site.media_deleted'));
    }
}
