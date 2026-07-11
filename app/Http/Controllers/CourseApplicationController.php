<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseApplication;
use App\Models\CourseApplicationForm;
use App\Models\User;
use App\Services\CourseApplicationFormService;
use App\Services\CourseApplicationReviewService;
use App\Services\CourseApplicationService;
use App\Services\CourseApplicationValidationService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseApplicationController extends Controller
{
    public function __construct(
        private CourseApplicationService $applications,
        private CourseApplicationFormService $forms,
        private CourseApplicationValidationService $validation,
        private CourseApplicationReviewService $review,
        private StudentRosterService $roster,
    ) {}

    public function index()
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        return view('course-applications.index', $this->availableCoursesData($user));
    }

    /** @return array<string, mixed> */
    private function availableCoursesData(User $user): array
    {
        $enrolledCourses = $this->roster->studentEnrolledCourses($user);
        $enrolledIds = $enrolledCourses->pluck('course_id');

        $openForms = CourseApplicationForm::query()
            ->where('is_enabled', true)
            ->with('course')
            ->orderBy('title')
            ->get()
            ->filter(fn (CourseApplicationForm $form) => ! $enrolledIds->contains($form->course_id));

        $applicationStatuses = [];
        foreach ($openForms as $form) {
            $applicationStatuses[$form->course_id] = $this->applications->courseApplicationStatus($user, $form->course_id);
        }

        return compact('enrolledCourses', 'openForms', 'applicationStatuses');
    }

    public function apply(Request $request, string $course)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $courseModel = Course::findOrFail($course);
        $form = CourseApplicationForm::forCourse($courseModel);

        if (! $form || ! $form->is_enabled) {
            return redirect()->route('available-courses.index')
                ->with('warning', __('course_applications.form_not_enabled'));
        }

        if ($this->applications->isApprovedForCourse($user, $courseModel->course_id)) {
            return redirect()->route('curriculum.show', $courseModel->course_id);
        }

        $latest = $this->applications->latestForUserCourse($user, $courseModel);
        if ($latest && in_array($latest->status, [
            CourseApplication::STATUS_PENDING_REVIEW,
            CourseApplication::STATUS_REJECTED,
        ], true)) {
            return redirect()->route('courses.application.status', $courseModel->course_id);
        }

        $form->load(['steps.fields']);
        $steps = $form->steps;
        $stepIndex = max(0, min((int) $request->query('step', 0), max(0, $steps->count() - 1)));
        $currentStep = $steps->get($stepIndex);

        return view('course-applications.apply', compact(
            'courseModel',
            'form',
            'steps',
            'stepIndex',
            'currentStep',
            'latest'
        ));
    }

    public function store(Request $request, string $course)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $courseModel = Course::findOrFail($course);
        $form = CourseApplicationForm::forCourse($courseModel);

        if (! $form || ! $form->is_enabled) {
            abort(404);
        }

        if ($this->applications->isApprovedForCourse($user, $courseModel->course_id)) {
            return redirect()->route('curriculum.show', $courseModel->course_id);
        }

        $rules = $this->validation->rulesForForm($form);
        $validated = $request->validate($rules);
        $latest = $this->applications->latestForUserCourse($user, $courseModel);

        $snapshot = $this->validation->buildSnapshot(
            $form,
            $request,
            $validated,
            $courseModel->course_id,
            $user->user_id,
            $latest?->snapshot
        );

        if ($latest && $latest->status === CourseApplication::STATUS_NEEDS_CORRECTION) {
            $this->review->resubmit($user, $latest, $snapshot);
        } else {
            $this->applications->createFromSubmission($user, $form, $snapshot);
        }

        return redirect()
            ->route('courses.application.status', $courseModel->course_id)
            ->with('success', __('course_applications.submitted_success'));
    }

    public function status(string $course)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $courseModel = Course::findOrFail($course);
        $application = $this->applications->latestForUserCourse($user, $courseModel);
        $form = CourseApplicationForm::forCourse($courseModel);

        return view('course-applications.status', compact('courseModel', 'application', 'form'));
    }

    public function edit(string $course)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $courseModel = Course::findOrFail($course);
        $status = $this->applications->courseApplicationStatus($user, $courseModel->course_id);

        if ($status !== CourseApplication::STATUS_NEEDS_CORRECTION) {
            return redirect()->route('courses.application.status', $courseModel->course_id);
        }

        $form = CourseApplicationForm::forCourse($courseModel);
        if (! $form) {
            abort(404);
        }

        $application = $this->applications->latestForUserCourse($user, $courseModel);
        $application?->load('fieldReviews');
        $form->load(['steps.fields']);

        $rejectedFields = $application?->fieldReviews
            ->where('status', 'rejected')
            ->pluck('field_key')
            ->all() ?? [];

        return view('course-applications.edit', compact(
            'courseModel',
            'form',
            'application',
            'rejectedFields'
        ));
    }

    public function update(Request $request, string $course)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $courseModel = Course::findOrFail($course);
        $status = $this->applications->courseApplicationStatus($user, $courseModel->course_id);

        if ($status !== CourseApplication::STATUS_NEEDS_CORRECTION) {
            return redirect()->route('courses.application.status', $courseModel->course_id);
        }

        $form = CourseApplicationForm::forCourse($courseModel);
        if (! $form) {
            abort(404);
        }

        $application = $this->applications->latestForUserCourse($user, $courseModel);
        $rules = $this->validation->rulesForForm($form);
        $validated = $request->validate($rules);

        $snapshot = $this->validation->buildSnapshot(
            $form,
            $request,
            $validated,
            $courseModel->course_id,
            $user->user_id,
            $application?->snapshot
        );

        $this->review->resubmit($user, $application, $snapshot);

        return redirect()
            ->route('courses.application.status', $courseModel->course_id)
            ->with('success', __('course_applications.resubmitted_success'));
    }
}
