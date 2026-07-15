<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesStudentCourse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumController extends Controller
{
    use AuthorizesStudentCourse;

    public function show(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCoursePermission($user, $course, 'curriculum.view');

        $course->load([
            'modules.courseSessions.lectures.materials',
            'modules.lectures.materials',
        ]);

        $modules = $course->modules->map(function ($module) {
            $sessions = ($module->courseSessions ?? collect())->map(fn ($session) => [
                'session_id' => $session->session_id,
                'session_title' => $session->session_title,
                'session_date' => $session->session_date,
                'lectures' => ($session->lectures ?? collect())->map(fn ($lecture) => [
                    'lecture_id' => $lecture->lecture_id ?? $lecture->getKey(),
                    'title' => $lecture->title ?? $lecture->lecture_title ?? null,
                    'materials' => ($lecture->materials ?? collect())->map(fn ($m) => [
                        'material_id' => $m->getKey(),
                        'title' => $m->title ?? $m->file_name ?? null,
                        'url' => $m->file_path ? asset('storage/'.$m->file_path) : ($m->url ?? null),
                    ])->values(),
                ])->values(),
            ])->values();

            return [
                'module_id' => $module->module_id,
                'title' => $module->title,
                'description' => $module->description,
                'status' => $module->pivot->status ?? null,
                'order_index' => $module->pivot->order_index ?? null,
                'sessions' => $sessions,
            ];
        })->values();

        return response()->json([
            'data' => [
                'course_id' => $course->course_id,
                'title' => method_exists($course, 'localizedTitle')
                    ? $course->localizedTitle()
                    : $course->title,
                'modules' => $modules,
            ],
        ]);
    }
}
