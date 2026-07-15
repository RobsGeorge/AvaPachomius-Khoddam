<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesStudentCourse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    use AuthorizesStudentCourse;

    public function index(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCoursePermission($user, $course, 'curriculum.view');

        $sessions = Session::query()
            ->with('module')
            ->where('course_id', $course->course_id)
            ->orderByDesc('session_date')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $sessions->map(fn (Session $session) => [
                'session_id' => $session->session_id,
                'course_id' => $session->course_id,
                'module_id' => $session->module_id,
                'module_title' => $session->module?->title,
                'session_title' => $session->session_title,
                'session_date' => $session->session_date,
                'session_start_time' => $session->session_start_time,
            ])->values(),
        ]);
    }
}
