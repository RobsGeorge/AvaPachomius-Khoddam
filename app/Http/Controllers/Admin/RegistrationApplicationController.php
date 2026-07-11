<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\RegistrationApplication;
use App\Models\RegistrationApplicationFieldReview;
use App\Models\RegistrationReviewTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\CourseRoleAssignmentService;
use App\Services\RegistrationReviewMailService;
use App\Services\RegistrationReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegistrationApplicationController extends Controller
{
    public function __construct(
        private RegistrationReviewService $review,
        private CourseRoleAssignmentService $roles,
        private RegistrationReviewMailService $mail
    ) {}

    public function index(Request $request)
    {
        $filter = $request->query('filter');

        $query = RegistrationApplication::query()
            ->with(['user'])
            ->latest('submitted_at');

        if ($filter && in_array($filter, RegistrationApplication::statuses(), true)) {
            $query->where('status', $filter);
        }

        $applications = $query->get();

        $counts = [];
        foreach (RegistrationApplication::statuses() as $status) {
            $counts[$status] = RegistrationApplication::query()->where('status', $status)->count();
        }

        return view('admin.registration-applications.index', compact('applications', 'filter', 'counts'));
    }

    public function show(RegistrationApplication $application)
    {
        $application->load(['user', 'fieldReviews', 'reviewer']);

        $fieldReviews = $application->fieldReviews->keyBy('field_key');
        $courses = $this->roles->coursesForPicker();
        $roles = $this->roles->rolesForPicker();

        return view('admin.registration-applications.show', compact(
            'application',
            'fieldReviews',
            'courses',
            'roles'
        ));
    }

    public function requestCorrections(Request $request, RegistrationApplication $application)
    {
        $fieldInput = $this->validatedFieldInput($request);
        $admin = Auth::user();
        if (! $admin instanceof User) {
            abort(403);
        }

        $this->review->requestCorrections($application, $admin, $fieldInput);

        return redirect()
            ->route('admin.registration-applications.show', $application)
            ->with('success', __('registration_review.corrections_requested'));
    }

    public function approve(Request $request, RegistrationApplication $application)
    {
        $fieldInput = $this->validatedFieldInput($request);
        $admin = Auth::user();
        if (! $admin instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'course_id' => ['required', 'exists:course,course_id'],
            'role_id' => ['required', 'exists:roles,role_id'],
            'allow_rejected_fields' => ['sometimes', 'boolean'],
        ]);

        $this->review->approve(
            $application,
            $admin,
            $fieldInput,
            [[
                'course_id' => (int) $validated['course_id'],
                'role_id' => (int) $validated['role_id'],
            ]],
            $request->boolean('allow_rejected_fields')
        );

        return redirect()
            ->route('admin.registration-applications.index', ['filter' => RegistrationApplication::STATUS_APPROVED])
            ->with('success', __('registration_review.application_approved'));
    }

    public function reject(Request $request, RegistrationApplication $application)
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
            ->route('admin.registration-applications.index', ['filter' => RegistrationApplication::STATUS_REJECTED])
            ->with('success', __('registration_review.application_rejected'));
    }

    public function restore(Request $request, RegistrationApplication $application)
    {
        $validated = $request->validate([
            'target_status' => ['required', 'in:pending_review,needs_correction'],
        ]);

        $admin = Auth::user();
        if (! $admin instanceof User) {
            abort(403);
        }

        $this->review->restore($application, $admin, $validated['target_status']);

        return back()->with('success', __('registration_review.application_restored'));
    }

    public function templates()
    {
        $this->mail->ensureDefaults();

        $templates = RegistrationReviewTemplate::query()
            ->orderBy('template_key')
            ->orderBy('locale')
            ->get()
            ->groupBy('template_key');

        return view('admin.registration-applications.templates', compact('templates'));
    }

    public function updateTemplates(Request $request)
    {
        $validated = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*.subject' => ['required', 'string', 'max:255'],
            'templates.*.body_html' => ['required', 'string'],
        ]);

        foreach ($validated['templates'] as $id => $payload) {
            RegistrationReviewTemplate::query()
                ->whereKey($id)
                ->update([
                    'subject' => $payload['subject'],
                    'body_html' => $payload['body_html'],
                ]);
        }

        return back()->with('success', __('registration_review.templates_saved'));
    }

    /** @return array<string, array{status?: string, comment?: string|null}> */
    private function validatedFieldInput(Request $request): array
    {
        $input = [];

        foreach (RegistrationApplication::REVIEWABLE_FIELDS as $field) {
            $status = $request->input("fields.{$field}.status", RegistrationApplicationFieldReview::STATUS_ACCEPTED);
            $input[$field] = [
                'status' => $status === RegistrationApplicationFieldReview::STATUS_REJECTED
                    ? RegistrationApplicationFieldReview::STATUS_REJECTED
                    : RegistrationApplicationFieldReview::STATUS_ACCEPTED,
                'comment' => $request->input("fields.{$field}.comment"),
            ];
        }

        return $input;
    }
}
