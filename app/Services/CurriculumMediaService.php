<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Course;
use App\Models\Lecture;
use App\Models\LectureMaterial;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CurriculumMediaService
{
    public function __construct(
        private ChurchStorageQuotaService $quota,
    ) {}

    /** @return list<string> */
    public function allowedMimes(): array
    {
        return (array) config('curriculum.allowed_mimes', []);
    }

    public function maxUploadKb(): int
    {
        return (int) config('curriculum.max_upload_kb', 20480);
    }

    public function diskName(): string
    {
        return (string) config('curriculum.disk', 'curriculum');
    }

    public function uploadForCourse(
        UploadedFile $file,
        Course $course,
        User $uploader,
        ?Church $church = null,
    ): MediaAsset {
        $this->validateUpload($file);

        $church ??= Church::query()->find($course->church_id);
        $this->quota->assertCanStore($church, (int) $file->getSize());

        $disk = $this->diskName();
        $churchId = (int) ($church?->church_id ?? $course->church_id ?? 0);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = "churches/{$churchId}/curriculum/{$course->course_id}/{$filename}";

        $storedPath = $file->storeAs(
            dirname($path),
            basename($path),
            $disk
        );

        if (! $storedPath) {
            throw ValidationException::withMessages([
                'file' => [__('curriculum.upload_failed')],
            ]);
        }

        $asset = MediaAsset::create([
            'church_id' => $churchId ?: null,
            'disk' => $disk,
            'path' => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $uploader->user_id,
            'context' => MediaAsset::CONTEXT_CURRICULUM,
        ]);

        $this->quota->incrementUsed($church, $asset->size_bytes);

        return $asset;
    }

    public function deleteAsset(MediaAsset $asset, ?User $actor = null): void
    {
        if ($asset->trashed()) {
            return;
        }

        $church = $asset->church;
        $size = (int) $asset->size_bytes;

        if (Storage::disk($asset->disk)->exists($asset->path)) {
            Storage::disk($asset->disk)->delete($asset->path);
        }

        $asset->delete();

        $this->quota->decrementUsed($church, $size);

        AuditLogService::recordEvent('curriculum.media.deleted', [
            'media_id' => $asset->media_id,
            'path' => $asset->path,
            'size_bytes' => $size,
            'actor_id' => $actor?->user_id,
        ]);
    }

    public function replaceSlidesMedia(Lecture $lecture, ?MediaAsset $newAsset): void
    {
        $previous = $lecture->slidesMedia;
        if ($previous && (! $newAsset || $previous->media_id !== $newAsset->media_id)) {
            $this->deleteAsset($previous);
        }

        $lecture->slides_media_id = $newAsset?->media_id;
        $lecture->slides_link = null;
    }

    public function clearSlidesLink(Lecture $lecture): void
    {
        if ($lecture->slides_media_id) {
            $media = $lecture->slidesMedia;
            $lecture->slides_media_id = null;
            $lecture->save();
            if ($media) {
                $this->deleteAsset($media);
            }
        }
    }

    /** @return list<Course> */
    public function coursesForMedia(MediaAsset $media): array
    {
        $lecture = Lecture::query()
            ->where('slides_media_id', $media->media_id)
            ->with('module.courses')
            ->first();

        if ($lecture) {
            return $lecture->module?->courses?->all() ?? [];
        }

        $material = LectureMaterial::query()
            ->where('media_id', $media->media_id)
            ->with('lecture.module.courses')
            ->first();

        if ($material?->lecture?->module) {
            return $material->lecture->module->courses->all();
        }

        return [];
    }

    public function userCanAccessMedia(MediaAsset $media, User $user): bool
    {
        if ($user->is_superadmin) {
            return true;
        }

        $resolver = app(CoursePermissionResolver::class);

        foreach ($this->coursesForMedia($media) as $course) {
            if ($resolver->canInCourse($user, 'curriculum.view', $course)
                || $resolver->canInCourse($user, 'curriculum.manage', $course)) {
                return true;
            }
        }

        return false;
    }

    private function validateUpload(UploadedFile $file): void
    {
        $mimes = implode(',', $this->allowedMimes());
        $max = $this->maxUploadKb();

        validator(
            ['file' => $file],
            [
                'file' => ['required', 'file', 'mimes:'.$mimes, 'max:'.$max],
            ],
            [
                'file.mimes' => __('curriculum.invalid_file_type'),
                'file.max' => __('curriculum.file_too_large', ['max' => (int) round($max / 1024)]),
            ]
        )->validate();
    }
}
