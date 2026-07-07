<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    public function index()
    {
        $assignments = Assignment::orderBy('due_date', 'desc')->get();
        $studentSubmissions = collect();
        $submissionCounts = collect();

        if (Auth::check()) {
            if ($this->userHasRole('student')) {
                $studentSubmissions = AssignmentSubmission::where('user_id', Auth::id())
                    ->whereIn('assignment_id', $assignments->pluck('assignment_id'))
                    ->get()
                    ->keyBy('assignment_id');
            }

            if ($this->userHasRole('admin') || $this->userHasRole('instructor')) {
                $submissionCounts = AssignmentSubmission::query()
                    ->selectRaw('assignment_id, COUNT(*) as count')
                    ->groupBy('assignment_id')
                    ->pluck('count', 'assignment_id');
            }
        }

        return view('assignments.index', compact('assignments', 'studentSubmissions', 'submissionCounts'));
    }

    public function create()
    {
        return view('assignments.create');
    }

    public function store(Request $request)
    {
        Log::info('Assignment creation request received', [
            'all_data' => $request->all()
        ]);

        $validated = $request->validate([
            'assignment_name' => 'required|string|max:255',
            'assignment_description' => 'required|string',
            'total_points' => 'required|integer|min:1',
            'due_date' => 'required|date|after:now',
            'instructions' => 'nullable|string',
            'resources' => 'nullable|string',
        ]);

        Log::info('Validated assignment data', [
            'validated_data' => $validated
        ]);

        try {
            Log::info('Attempting to create assignment', [
                'data' => $validated
            ]);

            $assignment = Assignment::create($validated);

            Log::info('Assignment creation result', [
                'assignment' => $assignment->toArray(),
                'exists' => $assignment->exists,
                'wasRecentlyCreated' => $assignment->wasRecentlyCreated
            ]);

            if (!$assignment->exists) {
                Log::error('Failed to create assignment', [
                    'data' => $validated,
                    'error' => 'Assignment was not saved to database'
                ]);
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
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', __('pages.assignment_create_error', ['message' => $e->getMessage()]));
        }
    }

    public function show(Assignment $assignment)
    {
        $submissions = $assignment->submissions()->with('user')->orderByDesc('submitted_at')->get();
        $currentSubmission = null;
        $canSubmit = false;
        $submissionOpen = $assignment->isSubmissionOpen();

        if ($this->userHasRole('student')) {
            $currentSubmission = $assignment->submissions()
                ->where('user_id', Auth::id())
                ->first();

            $canSubmit = $submissionOpen && $currentSubmission === null;
        }

        return view('assignments.show', compact(
            'assignment',
            'submissions',
            'currentSubmission',
            'canSubmit',
            'submissionOpen'
        ));
    }

    public function edit(Assignment $assignment)
    {
        return view('assignments.edit', compact('assignment'));
    }

    public function update(Request $request, Assignment $assignment)
    {
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
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', __('pages.assignment_update_failed'));
        }
    }

    public function destroy(Assignment $assignment)
    {
        try {
            $assignment->delete();
            return redirect()->route('assignments.index')
                ->with('success', __('pages.assignment_deleted'));
        } catch (\Exception $e) {
            Log::error('Error deleting assignment', [
                'assignment_id' => $assignment->assignment_id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->with('error', __('pages.assignment_delete_failed'));
        }
    }

    public function submit(Request $request, Assignment $assignment)
    {
        if (!$this->userHasRole('student')) {
            abort(403);
        }

        if (!$assignment->isSubmissionOpen()) {
            return back()->with('error', __('pages.submission_deadline_passed'));
        }

        if ($assignment->submissions()->where('user_id', Auth::id())->exists()) {
            return back()->with('error', __('pages.submission_already_exists'));
        }

        $validated = $request->validate([
            'submission_content' => 'required|string',
            'file' => 'required|file|mimes:pdf|max:' . Assignment::MAX_UPLOAD_KB,
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

        $validated = $request->validate([
            'points_earned' => 'required|integer|min:0|max:' . $assignment->total_points,
            'feedback' => 'nullable|string',
        ], [
            'points_earned.max' => __('pages.grade_exceeds_total', ['max' => $assignment->total_points]),
        ]);

        try {
            $teamSubmissions = $submission->isTeamSubmission()
                ? $submission->getMainSubmission()->teamSubmissions()->get()
                : collect([$submission]);

            foreach ($teamSubmissions as $teamSubmission) {
                $teamSubmission->update($validated);
            }

            return redirect()->route('assignments.show', $assignment)
                ->with('success', __('pages.grade_saved'));
        } catch (\Exception $e) {
            Log::error('Error grading assignment', [
                'submission_id' => $submission->submission_id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->with('error', __('pages.grade_failed'));
        }
    }

    public function dashboard()
    {
        $totalAssignments = Assignment::count();
        $upcomingAssignments = Assignment::where('due_date', '>', now())->count();
        $completedAssignments = Assignment::where('due_date', '<', now())->count();

        $upcomingAssignmentsList = Assignment::where('due_date', '>', now())
            ->orderBy('due_date')
            ->take(5)
            ->get();

        $recentSubmissions = AssignmentSubmission::with(['user', 'assignment'])
            ->orderBy('submitted_at', 'desc')
            ->take(5)
            ->get();

        return view('assignments.dashboard', compact(
            'totalAssignments',
            'upcomingAssignments',
            'completedAssignments',
            'upcomingAssignmentsList',
            'recentSubmissions'
        ));
    }

    public function updateSubmission(Request $request, AssignmentSubmission $submission)
    {
        if (!$this->userHasRole('student') || $submission->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$submission->assignment->isSubmissionOpen()) {
            return back()->with('error', __('pages.submission_deadline_passed'));
        }

        $validated = $request->validate([
            'submission_content' => 'required|string',
            'file' => 'nullable|file|mimes:pdf|max:' . Assignment::MAX_UPLOAD_KB,
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
                'error' => $e->getMessage()
            ]);
            return back()->with('error', __('pages.submission_update_failed'));
        }
    }

    private function userHasRole(string $role): bool
    {
        $user = Auth::user();

        return $user && $user->roles->contains('role_name', $role);
    }
}
