<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    public function index()
    {
        $assignments = Assignment::orderBy('due_date', 'desc')->get();
        return view('assignments.index', compact('assignments'));
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
                    ->with('error', 'حدث خطأ أثناء إنشاء الواجب. يرجى المحاولة مرة أخرى.');
            }

            return redirect()->route('assignments.index')
                ->with('success', 'تم إنشاء الواجب بنجاح');
        } catch (\Exception $e) {
            Log::error('Error creating assignment', [
                'data' => $validated,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء إنشاء الواجب: ' . $e->getMessage());
        }
    }

    public function show(Assignment $assignment)
    {
        $submissions = $assignment->submissions()->with('user')->get();
        return view('assignments.show', compact('assignment', 'submissions'));
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
                ->with('success', 'تم تحديث الواجب بنجاح');
        } catch (\Exception $e) {
            Log::error('Error updating assignment', [
                'assignment_id' => $assignment->assignment_id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء تحديث الواجب');
        }
    }

    public function destroy(Assignment $assignment)
    {
        try {
            $assignment->delete();
            return redirect()->route('assignments.index')
                ->with('success', 'تم حذف الواجب بنجاح');
        } catch (\Exception $e) {
            Log::error('Error deleting assignment', [
                'assignment_id' => $assignment->assignment_id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء حذف الواجب');
        }
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $validated = $request->validate([
            'submission_content' => 'required|string',
            'file' => 'nullable|file|max:10240', // 10MB max
        ]);

        try {
            $submission = new AssignmentSubmission([
                'submission_content' => $validated['submission_content'],
                'submitted_at' => now(),
            ]);

            if ($request->hasFile('file')) {
                $path = $request->file('file')->store('submissions');
                $submission->file_path = $path;
            }

            $submission->user_id = Auth::id();
            $assignment->submissions()->save($submission);

            return redirect()->route('assignments.show', $assignment)
                ->with('success', 'تم تقديم الواجب بنجاح');
        } catch (\Exception $e) {
            Log::error('Error submitting assignment', [
                'assignment_id' => $assignment->assignment_id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء تقديم الواجب');
        }
    }

    public function grade(Request $request, AssignmentSubmission $submission)
    {
        $validated = $request->validate([
            'points_earned' => 'required|integer|min:0',
            'feedback' => 'nullable|string',
        ]);

        try {
            $submission->update($validated);
            return redirect()->route('assignments.show', $submission->assignment)
                ->with('success', 'تم تقييم الواجب بنجاح');
        } catch (\Exception $e) {
            Log::error('Error grading assignment', [
                'submission_id' => $submission->submission_id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء تقييم الواجب');
        }
    }

    public function dashboard()
    {
        // Get statistics
        $totalAssignments = Assignment::count();
        $upcomingAssignments = Assignment::where('due_date', '>', now())->count();
        $completedAssignments = Assignment::where('due_date', '<', now())->count();

        // Get upcoming assignments
        $upcomingAssignmentsList = Assignment::where('due_date', '>', now())
            ->orderBy('due_date')
            ->take(5)
            ->get();

        // Get recent submissions
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
} 