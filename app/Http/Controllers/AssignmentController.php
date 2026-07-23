<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserNotification;
use App\Services\CoursePermissionResolver;
use App\Services\NotificationGeneratorService;
use App\Services\StudentRosterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    public function __construct(
        private StudentRosterService $roster,
        private CoursePermissionResolver $permissions,
        private NotificationGeneratorService $notifications,
    ) {}

    public function index(Request $request)
    {
        $canManage = $this->userCanManageAssignments();
        $section = $request->query('section', $canManage && ! $this->userCanViewAssignmentsList() ? 'manage' : 'list');
        if (! in_array($section, ['list', 'manage'], true)) {
            $section = 'list';
        }
        if ($section === 'manage' && ! $canManage) {
            $section = 'list';
        }

        $assignments = $this->assignmentsQuery()->get();
        $studentSubmissions = collect();
        $submissionCounts = collect();

        if (Auth::check()) {
            if (Auth::user()->isStudent()) {
                $studentSubmissions = AssignmentSubmission::where('user_id', Auth::id())
                    ->whereIn('assignment_id', $assignments->pluck('assignment_id'))
                    ->get()
                    ->keyBy('assignment_id');
            }

            if (Auth::user()->isInstructorOrAdmin() || $canManage) {
                $submissionCounts = AssignmentSubmission::query()
                    ->selectRaw('assignment_id, COUNT(*) as count')
                    ->whereIn('assignment_id', $assignments->pluck('assignment_id'))
                    ->groupBy('assignment_id')
                    ->pluck('count', 'assignment_id');
            }
        }

        $data = compact('assignments', 'studentSubmissions', 'submissionCounts', 'canManage', 'section');

        if ($canManage && $section === 'manage') {
            $data = array_merge($data, $this->managementSummary());
        }

        return view('assignments.index', $data);
    }

    public function create()
    {
        $this->authorizeAssignmentManage();

        $currentCourse = current_course();
        $courses = $currentCourse
            ? Course::whereKey($currentCourse->course_id)->orderBy('title')->get()
            : Course::orderBy('title')->get();

        return view('assignments.create', [
            'courses' => $courses,
            'defaultCourseId' => $currentCourse?->course_id,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAssignmentManage();

        $validated = $request->validate([
            'course_id' => 'required|exists:course,course_id',
            'assignment_name' => 'required|string|max:255',
            'assignment_description' => 'required|string',
            'total_points' => 'required|integer|min:1',
            'due_date' => 'required|date|after:now',
            'instructions' => 'nullable|string',
            'resources' => 'nullable|string',
            'delivery_mode' => 'required|in:'.Assignment::MODE_ONLINE.','.Assignment::MODE_OFFLINE,
        ]);

        $this->authorizeManageCourse((int) $validated['course_id']);

        try {
            $assignment = Assignment::create($validated);

            if (! $assignment->exists) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('pages.assignment_create_failed'));
            }

            return redirect()->route('assignments.index')
                ->with('success', __('pages.assignment_created'));
        } catch (\Exception $e) {
            Log::error('Error creating assignment', [
                'data' => $validated,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', __('pages.assignment_create_error', ['message' => $e->getMessage()]));
        }
    }

    public function show(Assignment $assignment)
    {
        $this->authorizeViewAssignment($assignment);

        $submissions = $assignment->submissions()->with('user')->orderByDesc('submitted_at')->get();
        $currentSubmission = null;
        $canSubmit = false;
        $submissionOpen = $assignment->isSubmissionOpen();
        $pendingStudents = collect();

        if (Auth::user()->isStudent()) {
            $currentSubmission = $assignment->submissions()
                ->where('user_id', Auth::id())
                ->first();

            $canSubmit = $assignment->isOnline() && $submissionOpen && $currentSubmission === null;
        }

        if (Auth::user()->isInstructorOrAdmin() && $assignment->isOffline()) {
            $submittedUserIds = $submissions->pluck('user_id')->all();
            $pendingStudents = $this->enrolledStudents($assignment)
                ->reject(fn (User $student) => in_array($student->user_id, $submittedUserIds, true))
                ->values();
        }

        $studentStatus = $this->resolveSubmissionStatus($assignment, $currentSubmission);

        return view('assignments.show', compact(
            'assignment',
            'submissions',
            'currentSubmission',
            'canSubmit',
            'submissionOpen',
            'studentStatus',
            'pendingStudents'
        ));
    }

    public function submissionStatusReport(Assignment $assignment)
    {
        $this->authorizeViewAssignment($assignment);
        if ($assignment->course_id) {
            $this->authorizeManageCourse((int) $assignment->course_id);
        }

        $students = $this->enrolledStudents($assignment);
        $submissions = $assignment->submissions()->with('user')->get()->keyBy('user_id');

        $rows = $students->map(function (User $student) use ($assignment, $submissions) {
            $submission = $submissions->get($student->user_id);

            return [
                'student' => $student,
                'submission' => $submission,
                'status' => $this->resolveSubmissionStatus($assignment, $submission),
            ];
        });

        $stats = [
            'total' => $rows->count(),
            'submitted' => $rows->whereIn('status', ['submitted', 'graded'])->count(),
            'not_submitted' => $rows->where('status', 'not_submitted')->count(),
            'overdue' => $rows->where('status', 'overdue')->count(),
            'graded' => $rows->where('status', 'graded')->count(),
        ];

        return view('assignments.status-report', compact('assignment', 'rows', 'stats'));
    }

    public function edit(Assignment $assignment)
    {
        $this->authorizeViewAssignment($assignment);
        if ($assignment->course_id) {
            $this->authorizeManageCourse((int) $assignment->course_id);
        }

        return view('assignments.edit', compact('assignment'));
    }

    public function update(Request $request, Assignment $assignment)
    {
        $this->authorizeViewAssignment($assignment);
        if ($assignment->course_id) {
            $this->authorizeManageCourse((int) $assignment->course_id);
        }

        $validated = $request->validate([
            'assignment_name' => 'required|string|max:255',
            'assignment_description' => 'required|string',
            'total_points' => 'required|integer|min:1',
            'due_date' => 'required|date',
            'instructions' => 'nullable|string',
            'resources' => 'nullable|string',
        ]);

        try {
            $assignment->update($validated);

            return redirect()->route('assignments.index')
                ->with('success', __('pages.assignment_updated'));
        } catch (\Exception $e) {
            Log::error('Error updating assignment', [
                'assignment_id' => $assignment->assignment_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', __('pages.assignment_update_failed'));
        }
    }

    public function destroy(Assignment $assignment)
    {
        $this->authorizeViewAssignment($assignment);
        if ($assignment->course_id) {
            $this->authorizeManageCourse((int) $assignment->course_id);
        }

        try {
            $assignment->delete();

            return redirect()->route('assignments.index')
                ->with('success', __('pages.assignment_deleted'));
        } catch (\Exception $e) {
            Log::error('Error deleting assignment', [
                'assignment_id' => $assignment->assignment_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', __('pages.assignment_delete_failed'));
        }
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $this->authorizeViewAssignment($assignment);

        if (! Auth::user()->isStudent()) {
            abort(403);
        }

        if ($assignment->isOffline()) {
            return back()->with('error', __('pages.assignment_offline_no_upload'));
        }

        if (! $assignment->isSubmissionOpen()) {
            return back()->with('error', __('pages.submission_deadline_passed'));
        }

        if ($assignment->submissions()->where('user_id', Auth::id())->exists()) {
            return back()->with('error', __('pages.submission_already_exists'));
        }

        $validated = $request->validate([
            'submission_content' => 'required|string',
            'file' => 'required|file|mimes:pdf|max:'.Assignment::MAX_UPLOAD_KB,
        ], [
            'submission_content.required' => __('pages.submission_content_required'),
            'file.required' => __('pages.pdf_required'),
            'file.mimes' => __('pages.pdf_only'),
            'file.max' => __('pages.pdf_too_large', ['max' => Assignment::MAX_UPLOAD_MB]),
        ]);

        $file = $request->file('file');
        $path = $file->store('submissions', 'public');

        $assignment->submissions()->create([
            'user_id' => Auth::id(),
            'submission_content' => $validated['submission_content'],
            'file_path' => $path,
            'submitted_at' => now(),
        ]);

        return redirect()->route('assignments.show', $assignment)
            ->with('success', __('pages.submission_success'));
    }

    public function grade(Request $request, AssignmentSubmission $submission)
    {
        $assignment = $submission->assignment;
        $this->authorizeViewAssignment($assignment);
        if ($assignment->course_id) {
            $this->authorizeManageCourse((int) $assignment->course_id);
        }

        $validated = $request->validate([
            'points_earned' => 'required|integer|min:0|max:'.$assignment->total_points,
            'feedback' => 'nullable|string',
        ], [
            'points_earned.max' => __('pages.grade_exceeds_total', ['max' => $assignment->total_points]),
        ]);

        try {
            $teamSubmissions = collect([$submission]);
            if (\Illuminate\Support\Facades\Schema::hasColumn('assignment_submission', 'team_submission_id')
                && $submission->isTeamSubmission()) {
                $main = $submission->getMainSubmission() ?? $submission;
                $team = $main->teamSubmissions()->get();
                $teamSubmissions = $team->isNotEmpty() ? $team->prepend($main)->unique('submission_id') : collect([$submission]);
            }

            $gradePayload = array_merge($validated, ['graded_at' => now()]);

            foreach ($teamSubmissions as $teamSubmission) {
                $teamSubmission->update($gradePayload);
            }

            return redirect()->route('assignments.show', $assignment)
                ->with('success', __('pages.grade_saved'));
        } catch (\Exception $e) {
            Log::error('Error grading assignment', [
                'submission_id' => $submission->submission_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', __('pages.grade_failed'));
        }
    }

    public function markReceived(Assignment $assignment, User $user)
    {
        $this->authorizeViewAssignment($assignment);
        if ($assignment->course_id) {
            $this->authorizeManageCourse((int) $assignment->course_id);
        }

        if (! $assignment->isOffline()) {
            return back()->with('error', __('pages.assignment_mark_received_online_only'));
        }

        $enrolledIds = $this->enrolledStudents($assignment)->pluck('user_id');
        if (! $enrolledIds->contains($user->user_id)) {
            return back()->with('error', __('pages.assignment_student_not_enrolled'));
        }

        $existing = $assignment->submissions()->where('user_id', $user->user_id)->first();
        if ($existing) {
            return redirect()->route('assignments.show', $assignment)
                ->with('success', __('pages.assignment_already_marked_received'));
        }

        $assignment->submissions()->create([
            'user_id' => $user->user_id,
            'submission_content' => null,
            'file_path' => null,
            'submitted_at' => now(),
        ]);

        return back()->with('success', __('pages.assignment_marked_received'));
    }

    public function remindUnsubmitted(Assignment $assignment)
    {
        $this->authorizeViewAssignment($assignment);
        if ($assignment->course_id) {
            $this->authorizeManageCourse((int) $assignment->course_id);
        }

        $students = $this->enrolledStudents($assignment);
        $submissions = $assignment->submissions()->get()->keyBy('user_id');
        $dayKey = now()->format('Y-m-d');
        $sent = 0;
        $skipped = 0;

        foreach ($students as $student) {
            $submission = $submissions->get($student->user_id);
            $status = $this->resolveSubmissionStatus($assignment, $submission);
            if (! in_array($status, ['not_submitted', 'overdue'], true)) {
                continue;
            }

            $title = __('notifications.generated.assignment_submission_reminder_title', [
                'title' => $assignment->assignment_name,
            ]);
            $body = __('notifications.generated.assignment_submission_reminder_body', [
                'date' => $assignment->due_date?->format('d/m/Y H:i'),
            ]);
            $actionUrl = route('assignments.show', $assignment);
            $dedupeKey = "assignment_submission_reminder:{$assignment->assignment_id}:{$student->user_id}:{$dayKey}";

            if (UserNotification::query()
                ->where('user_id', $student->user_id)
                ->where('dedupe_key', $dedupeKey)
                ->exists()) {
                $skipped++;

                continue;
            }

            $this->notifications->createOrUpdate(
                $student,
                'assignment_submission_reminder',
                $title,
                $body,
                $actionUrl,
                Assignment::class,
                (int) $assignment->assignment_id,
                metadata: [
                    'course_id' => $assignment->course_id,
                    'assignment_id' => $assignment->assignment_id,
                ],
                dedupeKey: $dedupeKey,
            );

            $sent++;
        }

        return back()->with('success', __('pages.assignment_reminders_sent', [
            'sent' => $sent,
            'skipped' => $skipped,
        ]));
    }

    public function dashboard()
    {
        $this->authorizeAssignmentManage();

        return redirect()->route('assignments.index', ['section' => 'manage']);
    }

    public function updateSubmission(Request $request, AssignmentSubmission $submission)
    {
        $this->authorizeViewAssignment($submission->assignment);

        if (! Auth::user()->isStudent() || $submission->user_id !== Auth::id()) {
            abort(403);
        }

        if ($submission->assignment->isOffline()) {
            return back()->with('error', __('pages.assignment_offline_no_upload'));
        }

        if (! $submission->assignment->isSubmissionOpen()) {
            return back()->with('error', __('pages.submission_deadline_passed'));
        }

        $validated = $request->validate([
            'submission_content' => 'required|string',
            'file' => 'nullable|file|mimes:pdf|max:'.Assignment::MAX_UPLOAD_KB,
        ], [
            'submission_content.required' => __('pages.submission_content_required'),
            'file.mimes' => __('pages.pdf_only'),
            'file.max' => __('pages.pdf_too_large', ['max' => Assignment::MAX_UPLOAD_MB]),
        ]);

        try {
            $submission->submission_content = $validated['submission_content'];

            if ($request->hasFile('file')) {
                if ($submission->file_path) {
                    Storage::disk('public')->delete($submission->file_path);
                }

                $path = $request->file('file')->store('submissions', 'public');
                $submission->file_path = $path;
            }

            $submission->submitted_at = now();
            $submission->save();

            return redirect()->route('assignments.show', $submission->assignment)
                ->with('success', __('pages.submission_updated'));
        } catch (\Exception $e) {
            Log::error('Error updating submission', [
                'submission_id' => $submission->submission_id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', __('pages.submission_update_failed'));
        }
    }

    /** @return array<string, mixed> */
    private function managementSummary(): array
    {
        $assignmentsQuery = $this->assignmentsQuery();
        $assignmentIds = (clone $assignmentsQuery)->pluck('assignment_id');

        $totalAssignments = (clone $assignmentsQuery)->count();
        $upcomingAssignments = (clone $assignmentsQuery)->where('due_date', '>', now())->count();
        $completedAssignments = (clone $assignmentsQuery)->where('due_date', '<', now())->count();

        $upcomingAssignmentsList = (clone $assignmentsQuery)
            ->where('due_date', '>', now())
            ->orderBy('due_date')
            ->take(5)
            ->get();

        $recentSubmissions = AssignmentSubmission::with(['user', 'assignment'])
            ->whereIn('assignment_id', $assignmentIds)
            ->orderBy('submitted_at', 'desc')
            ->take(5)
            ->get();

        $course = current_course();
        $totalStudents = $course
            ? $this->roster->enrolledStudents($course)->count()
            : $this->enrolledStudents()->count();

        $assignmentSummaries = (clone $assignmentsQuery)->get()->map(function (Assignment $assignment) use ($totalStudents) {
            $submittedCount = $assignment->submissions()->count();
            $gradedCount = $assignment->submissions()->whereNotNull('points_earned')->count();

            return [
                'assignment' => $assignment,
                'submitted' => $submittedCount,
                'not_submitted' => max($totalStudents - $submittedCount, 0),
                'graded' => $gradedCount,
            ];
        });

        return compact(
            'totalAssignments',
            'upcomingAssignments',
            'completedAssignments',
            'upcomingAssignmentsList',
            'recentSubmissions',
            'assignmentSummaries',
            'totalStudents'
        );
    }

    private function userCanManageAssignments(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->is_superadmin ?? false) {
            return true;
        }

        $course = current_course();
        if ($course && $this->permissions->canInCourse($user, 'assignment.manage', $course)) {
            return true;
        }

        return $user->isInstructorOrAdmin();
    }

    private function userCanViewAssignmentsList(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->is_superadmin ?? false) {
            return true;
        }

        $course = current_course();
        if ($course && $this->permissions->canInCourse($user, 'assignment.view', $course)) {
            return true;
        }

        return $user->isStudent() || $user->isInstructorOrAdmin();
    }

    private function assignmentsQuery(): Builder
    {
        $query = Assignment::query()->orderBy('due_date', 'desc');

        $currentCourse = current_course();
        if ($currentCourse) {
            $query->where('course_id', $currentCourse->course_id);
        }

        return $query;
    }

    private function enrolledStudents(?Assignment $assignment = null): Collection
    {
        $courseId = $assignment?->course_id ?? current_course()?->course_id;

        if ($courseId) {
            $course = Course::find($courseId);

            return $course ? $this->roster->enrolledStudents($course) : collect();
        }

        $studentRoleIds = Role::studentRoleIds();

        if ($studentRoleIds->isEmpty()) {
            return collect();
        }

        $studentIds = UserCourseRole::query()
            ->whereIn('role_id', $studentRoleIds)
            ->pluck('user_id')
            ->unique();

        return User::query()
            ->whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->orderBy('second_name')
            ->get();
    }

    private function authorizeAssignmentManage(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($user->is_superadmin ?? false) {
            return;
        }

        $course = current_course();
        if ($course && $this->permissions->canInCourse($user, 'assignment.manage', $course)) {
            return;
        }

        if ($user->isInstructorOrAdmin()) {
            return;
        }

        abort(403);
    }

    private function authorizeManageCourse(?int $courseId): void
    {
        if (! $courseId) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($user->is_superadmin ?? false) {
            return;
        }

        $course = Course::findOrFail($courseId);
        if ($this->permissions->canInCourse($user, 'assignment.manage', $course) || $user->isInstructorOrAdmin((string) $courseId)) {
            return;
        }

        abort(403);
    }

    private function authorizeViewAssignment(Assignment $assignment): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($user->is_superadmin ?? false) {
            return;
        }

        if (! $assignment->course_id) {
            return;
        }

        $this->roster->authorizeCourse($user, (string) $assignment->course_id);

        $current = current_course();
        if ($current && (int) $current->course_id !== (int) $assignment->course_id) {
            abort(404);
        }
    }

    private function resolveSubmissionStatus(Assignment $assignment, ?AssignmentSubmission $submission): string
    {
        if ($submission && $submission->points_earned !== null) {
            return 'graded';
        }

        if ($submission) {
            return 'submitted';
        }

        return $assignment->isSubmissionOpen() ? 'not_submitted' : 'overdue';
    }
}
