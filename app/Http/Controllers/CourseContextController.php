<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\CourseContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseContextController extends Controller
{
    public function __construct(
        private CourseContextService $courseContext,
    ) {}

    public function show(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $courses = $this->courseContext->selectableCourses($user);
        $current = $this->courseContext->currentCourse($user);

        return view('courses.select', [
            'courses' => $courses,
            'currentCourse' => $current,
            'intended' => $request->query('intended'),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:course,course_id',
            'intended' => 'nullable|string',
        ]);

        $this->courseContext->setCurrentCourse($user, (int) $validated['course_id']);

        $intended = $validated['intended'] ?? null;
        if ($intended && $this->isSafeLocalRedirect($intended)) {
            return redirect($intended)->with('success', __('course_context.course_selected'));
        }

        return redirect()
            ->route('dashboard')
            ->with('success', __('course_context.course_selected'));
    }

    private function isSafeLocalRedirect(string $url): bool
    {
        if (! str_starts_with($url, '/')) {
            return false;
        }

        return ! str_starts_with($url, '//');
    }
}
