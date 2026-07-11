<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LectureMaterial;

class LectureMaterialController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'lecture_id' => 'required|exists:lectures,lecture_id',
            'title'      => 'required|string|max:150',
            'link'       => 'required|url|max:500',
        ]);

        LectureMaterial::create($request->only('lecture_id', 'title', 'link'));

        $lecture = \App\Models\Lecture::query()->find($request->lecture_id);
        if ($lecture) {
            app(\App\Services\NotificationScannerService::class)->notifyNewLecture($lecture);
        }

        return redirect()
            ->route('lectures.edit', $request->lecture_id)
            ->with('success', 'تمت إضافة المادة بنجاح');
    }

    public function destroy(string $id)
    {
        $material = LectureMaterial::findOrFail($id);
        $lectureId = $material->lecture_id;
        $material->delete();

        return redirect()
            ->route('lectures.edit', $lectureId)
            ->with('success', 'تم حذف المادة');
    }
}
