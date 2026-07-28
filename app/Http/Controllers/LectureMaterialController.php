<?php

namespace App\Http\Controllers;

use App\Models\Lecture;
use App\Models\LectureMaterial;
use App\Services\CurriculumMediaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LectureMaterialController extends Controller
{
    public function __construct(
        private CurriculumMediaService $media,
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'lecture_id' => 'required|exists:lectures,lecture_id',
            'title'      => 'required|string|max:150',
            'source_type' => ['required', Rule::in([
                LectureMaterial::SOURCE_EXTERNAL_LINK,
                LectureMaterial::SOURCE_HOSTED_FILE,
            ])],
            'link' => 'nullable|url|max:500|required_if:source_type,external_link',
            'file' => 'nullable|file|required_if:source_type,hosted_file',
        ]);

        $lecture = Lecture::with('module.courses')->findOrFail($request->lecture_id);
        $course = $lecture->module->courses->first();

        $data = [
            'lecture_id' => $lecture->lecture_id,
            'title' => $request->title,
            'source_type' => $request->source_type,
            'link' => null,
            'media_id' => null,
        ];

        if ($request->source_type === LectureMaterial::SOURCE_EXTERNAL_LINK) {
            $data['link'] = $request->link;
        } else {
            $data['link'] = '';
            if (! $course) {
                throw ValidationException::withMessages([
                    'file' => [__('curriculum.course_required_for_upload')],
                ]);
            }

            try {
                $asset = $this->media->uploadForCourse($request->file('file'), $course, $request->user());
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['file' => [$e->getMessage()]]);
            }

            $data['media_id'] = $asset->media_id;
        }

        $material = LectureMaterial::create($data);

        app(\App\Services\NotificationScannerService::class)->notifyNewLecture($lecture);

        return redirect()
            ->route('lectures.edit', $request->lecture_id)
            ->with('success', __('curriculum.material_added'));
    }

    public function destroy(string $id)
    {
        $material = LectureMaterial::with('media')->findOrFail($id);
        $lectureId = $material->lecture_id;

        if ($material->isHostedFile() && $material->media) {
            $this->media->deleteAsset($material->media, auth()->user());
        }

        $material->delete();

        return redirect()
            ->route('lectures.edit', $lectureId)
            ->with('success', __('curriculum.material_deleted'));
    }
}
