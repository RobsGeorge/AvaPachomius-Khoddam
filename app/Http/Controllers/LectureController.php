<?php

namespace App\Http\Controllers;

use App\Models\Lecture;
use App\Models\Session;
use App\Services\ChurchStorageQuotaService;
use App\Services\CurriculumMediaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LectureController extends Controller
{
    public function __construct(
        private CurriculumMediaService $media,
        private ChurchStorageQuotaService $quota,
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'session_id'   => 'required|exists:session,session_id',
            'course_id'    => 'required|exists:course,course_id',
            'module_id'    => 'required|exists:modules,module_id',
            'title'        => 'required|string|max:150',
            'lecture_date' => 'nullable|date',
            'video_link'   => 'nullable|url|max:500',
            'slides_link'  => 'nullable|url|max:500',
            'notes'        => 'nullable|string',
            'order_index'  => 'nullable|integer|min:0',
        ]);

        $session = $this->resolveSessionForModule(
            (int) $request->session_id,
            (int) $request->module_id,
            (int) $request->course_id
        );

        $lecture = Lecture::create([
            'module_id'    => $request->module_id,
            'session_id'   => $session->session_id,
            'title'        => $request->title,
            'week_number'  => $session->week_number ?? 1,
            'lecture_date' => $request->lecture_date ?? $session->session_date,
            'video_link'   => $request->video_link,
            'slides_link'  => $request->slides_link,
            'notes'        => $request->notes,
            'order_index'  => $request->order_index ?? 0,
        ]);

        app(\App\Services\NotificationScannerService::class)->notifyNewLecture($lecture);

        return redirect()
            ->route('curriculum.admin', $request->course_id)
            ->with('success', __('pages.lecture_created_success'));
    }

    public function edit(string $id)
    {
        $lecture = Lecture::with('materials.media', 'slidesMedia', 'module.courses', 'module.courseSessions', 'session')
            ->findOrFail($id);

        $course = $lecture->module->courses->first();
        $church = $course ? \App\Models\Church::query()->find($course->church_id) : null;

        return view('lectures.edit', [
            'lecture' => $lecture,
            'storageQuota' => $this->quota->quotaBytes($church),
            'storageUsed' => $this->quota->usedBytes($church),
            'storageRemaining' => $this->quota->remainingBytes($church),
            'storagePercent' => $this->quota->usagePercent($church),
            'maxUploadMb' => (int) round($this->media->maxUploadKb() / 1024),
            'allowedMimes' => $this->media->allowedMimes(),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'session_id'   => 'required|exists:session,session_id',
            'title'        => 'required|string|max:150',
            'lecture_date' => 'nullable|date',
            'video_link'   => 'nullable|url|max:500',
            'slides_source' => ['nullable', Rule::in(['external_link', 'hosted_file'])],
            'slides_link'  => 'nullable|url|max:500|required_if:slides_source,external_link',
            'slides_file'  => 'nullable|file',
            'remove_slides_file' => 'nullable|boolean',
            'notes'        => 'nullable|string',
            'order_index'  => 'nullable|integer|min:0',
        ]);

        $lecture = Lecture::with('module.courses', 'slidesMedia')->findOrFail($id);
        $course = $lecture->module->courses->first();

        if ($request->boolean('remove_slides_file')) {
            $this->media->clearSlidesLink($lecture);
            $lecture->refresh();
        }

        $slidesSource = $request->input(
            'slides_source',
            $lecture->slides_media_id ? 'hosted_file' : 'external_link'
        );

        $slidesMediaId = $lecture->slides_media_id;
        $slidesLink = $lecture->slides_link;

        if ($slidesSource === 'hosted_file' && $request->hasFile('slides_file')) {
            if (! $course) {
                throw ValidationException::withMessages([
                    'slides_file' => [__('curriculum.course_required_for_upload')],
                ]);
            }

            try {
                $asset = $this->media->uploadForCourse($request->file('slides_file'), $course, $request->user());
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['slides_file' => [$e->getMessage()]]);
            }

            if ($lecture->slidesMedia && $lecture->slidesMedia->media_id !== $asset->media_id) {
                $this->media->deleteAsset($lecture->slidesMedia, $request->user());
            }

            $slidesMediaId = $asset->media_id;
            $slidesLink = null;
        } elseif ($slidesSource === 'external_link') {
            if ($lecture->slides_media_id && $lecture->slidesMedia) {
                $this->media->deleteAsset($lecture->slidesMedia, $request->user());
            }
            $slidesMediaId = null;
            $slidesLink = $request->slides_link;
        }

        $session = $this->resolveSessionForModule(
            (int) $request->session_id,
            (int) $lecture->module_id,
            $course ? (int) $course->course_id : null
        );

        $lecture->update([
            'session_id'   => $session->session_id,
            'title'        => $request->title,
            'week_number'  => $session->week_number ?? $lecture->week_number,
            'lecture_date' => $request->lecture_date,
            'video_link'   => $request->video_link,
            'slides_link'  => $slidesLink,
            'slides_media_id' => $slidesMediaId,
            'notes'        => $request->notes,
            'order_index'  => $request->order_index ?? 0,
        ]);

        return redirect()
            ->route('lectures.edit', $id)
            ->with('success', __('pages.lecture_updated_success'));
    }

    public function destroy(Request $request, string $id)
    {
        $lecture = Lecture::with('slidesMedia', 'materials.media')->findOrFail($id);
        $courseId = $request->input('course_id');

        if ($lecture->slidesMedia) {
            $this->media->deleteAsset($lecture->slidesMedia, $request->user());
        }

        foreach ($lecture->materials as $material) {
            if ($material->isHostedFile() && $material->media) {
                $this->media->deleteAsset($material->media, $request->user());
            }
        }

        $lecture->delete();

        return redirect()
            ->route('curriculum.admin', $courseId)
            ->with('success', __('pages.lecture_deleted_success'));
    }

    private function resolveSessionForModule(int $sessionId, int $moduleId, ?int $courseId): Session
    {
        $session = Session::where('session_id', $sessionId)
            ->where('module_id', $moduleId)
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session_id' => [__('pages.session_not_in_module')],
            ]);
        }

        if ($courseId !== null && (int) $session->course_id !== $courseId) {
            throw ValidationException::withMessages([
                'session_id' => [__('pages.session_not_in_course')],
            ]);
        }

        return $session;
    }
}
