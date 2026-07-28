<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Services\CurriculumMediaService;
use Illuminate\Support\Facades\Storage;

class CurriculumMediaController extends Controller
{
    public function __construct(
        private CurriculumMediaService $media,
    ) {}

    public function download(string $mediaId)
    {
        $asset = MediaAsset::query()->findOrFail($mediaId);
        $user = auth()->user();

        abort_unless($user && $this->media->userCanAccessMedia($asset, $user), 403);

        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        return Storage::disk($asset->disk)->download(
            $asset->path,
            $asset->original_filename,
            ['Content-Type' => $asset->mime_type]
        );
    }
}
