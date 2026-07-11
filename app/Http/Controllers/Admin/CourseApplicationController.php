<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseApplication;
use App\Models\CourseApplicationFieldReview;
use App\Models\User;
use App\Services\CourseApplicationReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseApplicationController extends Controller
{
    public function __construct(
        private CourseApplicationReviewService $review,
    ) {}

    public function index(Request $request)
    {
        $filter = $request->query('filter');
        $courseFilter = $request->query('course_id');

        $query = CourseApplication::query()
            ->with(['user', 'course'])
            ->latest('submitted_at');

        if ($filter && in_array($filter, CourseApplication::statuses(), true)) {
            $query->where('status', $filter);
        }

        if ($courseFilter) {
            $query->where('course_id', $courseFilter);
        }

        $applications = $query->get();
        $courses = Course::orderBy('title')->get();

        $counts = [];
        foreach (CourseApplication::statuses() as $status) {
            $counts[$status] = CourseApplication::query()->where('status', $status)->count();
        }

        return view('admin.course-applications.index', compact(
            'applications',
            'filter',
            'counts',
            'courses',
            'courseFilter'
        ));
    }

    public function show(CourseApplication $application)
    {
        $application->load(['user', 'fieldReviews', 'reviewer', 'course', 'form.steps.fields']);

        $fieldReviews = $application->fieldReviews->keyBy('field_key');
        $fieldLabels = [];
        foreach ($application->form?->steps ?? [] as $step) {
            foreach ($step->fields as $field) {
                $fieldLabels[$field->field_key] = $field;
            }
        }

        return view('admin.course-applications.show', compact(
            'application',
            'fieldReviews',
            'fieldLabels'
        ));
    }

    public function requestCorrections(Request $request, CourseApplication $application)
    {
        $fieldInput = $this->validatedFieldInput($request, $application);
        $admin = Auth::user();
        if (! $admin instanceof User) {
            abort(403);
        }

        $this->review->requestCorrections($application, $admin, $fieldInput);

        return redirect()
            ->route('admin.course-applications.show', $application)
            ->with('success', __('course_applications.corrections_requested'));
    }

    public function approve(Request $request, CourseApplication $application)
    {
        $fieldInput = $this->validatedFieldInput($request, $application);
        $admin = Auth::user();
        if (! $admin instanceof User) {
            abort(403);
        }

        $this->review->approve(
            $application,
            $admin,
            $fieldInput,
            $request->boolean('allow_rejected_fields')
        );

        return redirect()
            ->route('admin.course-applications.index', ['filter' => CourseApplication::STATUS_APPROVED])
            ->with('success', __('course_applications.application_approved'));
    }

    public function reject(Request $request, CourseApplication $application)
    {
        $validated = $request->validate([
            'overall_rejection_note' => ['required', 'string', 'max:2000'],
        ]);

        $admin = Auth::user();
        if (! $admin instanceof User) {
            abort(403);
        }

        $this->review->rejectApplication($application, $admin, $validated['overall_rejection_note']);

        return redirect()
            ->route('admin.course-applications.index', ['filter' => CourseApplication::STATUS_REJECTED])
            ->with('success', __('course_applications.application_rejected'));
    }

    public function restore(Request $request, CourseApplication $application)
    {
        $validated = $request->validate([
            'target_status' => ['required', 'in:pending_review,needs_correction'],
        ]);

        $admin = Auth::user();
        if (! $admin instanceof User) {
            abort(403);
        }

        $this->review->restore($application, $admin, $validated['target_status']);

        return back()->with('success', __('course_applications.application_restored'));
    }

    /** @return array<string, array{status?: string, comment?: string|null}> */
    private function validatedFieldInput(Request $request, CourseApplication $application): array
    {
        $input = [];

        foreach ($application->reviewableFieldKeys() as $fieldKey) {
            $status = $request->input("fields.{$fieldKey}.status", CourseApplicationFieldReview::STATUS_ACCEPTED);
            $input[$fieldKey] = [
                'status' => $status === CourseApplicationFieldReview::STATUS_REJECTED
                    ? CourseApplicationFieldReview::STATUS_REJECTED
                    : CourseApplicationFieldReview::STATUS_ACCEPTED,
                'comment' => $request->input("fields.{$fieldKey}.comment"),
            ];
        }

        return $input;
    }
}
