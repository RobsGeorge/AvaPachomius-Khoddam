<?php

namespace App\Http\Controllers;

use App\Models\ChurchService;
use App\Models\Course;
use App\Services\CourseContextService;
use App\Services\ServiceContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseContextController extends Controller
{
    public function __construct(
        private CourseContextService $courseContext,
        private ServiceContextService $serviceContext,
    ) {}

    public function show(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (ChurchService::tableReady() && $this->serviceContext->requiresServiceContext($user)) {
            $this->serviceContext->autoSelectSingleService($user);
            if (! $this->serviceContext->currentService($user)) {
                return redirect()->route('services.select', [
                    'intended' => $request->fullUrl(),
                ]);
            }
        }

        $currentService = ChurchService::tableReady()
            ? $this->serviceContext->currentService($user)
            : null;
        $courses = $this->courseContext->selectableCourses($user);
        $current = $this->courseContext->currentCourse($user);

        return view('courses.select', [
            'courses' => $courses,
            'currentCourse' => $current,
            'currentService' => $currentService,
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

    public function clear(Request $request)
    {
        $user = Auth::user();
        abort_unless($user && ($user->is_superadmin ?? false), 403);

        $this->courseContext->clearCurrentCourse();

        $intended = $request->input('intended');
        if (is_string($intended) && $this->isSafeLocalRedirect($intended)) {
            return redirect($intended)->with('success', __('course_context.system_wide_mode_active'));
        }

        return redirect()
            ->route('superadmin.index')
            ->with('success', __('course_context.system_wide_mode_active'));
    }

    private function isSafeLocalRedirect(string $url): bool
    {
        if (! str_starts_with($url, '/')) {
            return false;
        }

        return ! str_starts_with($url, '//');
    }
}
