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
        $currentSubmission = null;
        
        if (Auth::user()->roles->contains('role_name', 'student')) {
            $currentSubmission = $assignment->submissions()
                ->where('user_id', Auth::id())
                ->first();

            // Get all students for the team selection
            $students = \App\Models\User::whereHas('roles', function($query) {
                $query->where('role_name', 'student');
            })->get();
        }
        
        return view('assignments.show', compact('assignment', 'submissions', 'currentSubmission', 'students'));
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
        // Check if the deadline has passed
        if (now()->addHours(3) > $assignment->due_date) {
            return redirect()->back()
                ->with('error', 'انتهى موعد التسليم');
        }

        $validated = $request->validate([
            'team_members' => 'required|array|min:1',
            'team_members.*' => 'exists:users,id',
            'submission_content' => 'required|string',
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB max, PDF only
        ], [
            'team_members.required' => 'يجب اختيار عضو واحد على الأقل',
            'team_members.*.exists' => 'أحد الأعضاء المختارين غير موجود',
            'file.required' => 'يجب رفع ملف PDF لتقديم الواجب',
            'file.mimes' => 'يجب أن يكون الملف المرفق بصيغة PDF فقط',
            'file.max' => 'حجم الملف يجب أن لا يتجاوز 10 ميجابايت'
        ]);

        try {
            // Store the file first
            $path = $request->file('file')->store('submissions', 'public');

            // Create a submission for each team member
            foreach ($validated['team_members'] as $userId) {
                $submission = new AssignmentSubmission([
                    'submission_content' => $validated['submission_content'],
                    'submitted_at' => now(),
                    'assignment_id' => $assignment->assignment_id,
                    'user_id' => $userId,
                    'file_path' => $path,
                    'team_submission_id' => null // Will be set after first submission
                ]);

                $submission->save();

                // If this is the first submission, update all submissions with this ID
                if ($submission->id) {
                    AssignmentSubmission::where('assignment_id', $assignment->assignment_id)
                        ->where('submitted_at', $submission->submitted_at)
                        ->where('submission_content', $submission->submission_content)
                        ->where('file_path', $submission->file_path)
                        ->update(['team_submission_id' => $submission->id]);
                }
            }

            return redirect()->route('assignments.show', $assignment)
                ->with('success', 'تم تقديم الواجب بنجاح');
        } catch (\Exception $e) {
            Log::error('Error submitting assignment', [
                'assignment_id' => $assignment->assignment_id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->with('error', $e->getMessage());
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

    public function updateSubmission(Request $request, AssignmentSubmission $submission)
    {
        // Check if the user owns this submission
        if ($submission->user_id !== Auth::id()) {
            return redirect()->back()
                ->with('error', 'غير مصرح لك بتحديث هذا التسليم');
        }

        // Check if the deadline has passed
        if (now()->addHours(3) > $submission->assignment->due_date) {
            return redirect()->back()
                ->with('error', 'انتهى موعد التسليم');
        }

        $validated = $request->validate([
            'submission_content' => 'required|string',
            'file' => 'nullable|file|mimes:pdf|max:10240', // 10MB max, PDF only
        ], [
            'file.mimes' => 'يجب أن يكون الملف المرفق بصيغة PDF فقط',
            'file.max' => 'حجم الملف يجب أن لا يتجاوز 10 ميجابايت'
        ]);

        try {
            // Update all submissions in the team
            $teamSubmissions = AssignmentSubmission::where('team_submission_id', $submission->team_submission_id)
                ->orWhere('id', $submission->team_submission_id)
                ->get();

            foreach ($teamSubmissions as $teamSubmission) {
                $teamSubmission->submission_content = $validated['submission_content'];
                $teamSubmission->submitted_at = now();

                if ($request->hasFile('file')) {
                    // Delete old file if exists
                    if ($teamSubmission->file_path) {
                        Storage::disk('public')->delete($teamSubmission->file_path);
                    }
                    
                    // Store new file
                    $path = $request->file('file')->store('submissions', 'public');
                    $teamSubmission->file_path = $path;
                }

                $teamSubmission->save();
            }

            return redirect()->route('assignments.show', $submission->assignment)
                ->with('success', 'تم تحديث التسليم بنجاح');
        } catch (\Exception $e) {
            Log::error('Error updating submission', [
                'submission_id' => $submission->submission_id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء تحديث التسليم');
        }
    }
} 