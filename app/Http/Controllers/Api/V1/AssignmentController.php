<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesStudentCourse;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    use AuthorizesStudentCourse;

    public function index(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCoursePermission($user, $course, 'assignment.view');

        $assignments = Assignment::query()
            ->where('course_id', $course->course_id)
            ->orderByDesc('due_date')
            ->get();

        return response()->json([
            'data' => $assignments->map(fn (Assignment $a) => $this->serialize($a, $user))->values(),
        ]);
    }

    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($assignment->course_id, 404);
        $course = Course::findOrFail($assignment->course_id);
        $this->authorizeCoursePermission($user, $course, 'assignment.view');

        return response()->json(['data' => $this->serialize($assignment, $user, true)]);
    }

    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($assignment->course_id, 404);
        $course = Course::findOrFail($assignment->course_id);
        $this->authorizeCoursePermission($user, $course, 'assignment.submit');
        abort_unless($user->isStudent(), 403);
        abort_if($assignment->isOffline(), 422, __('pages.assignment_offline_no_upload'));
        abort_unless($assignment->isSubmissionOpen(), 422, __('pages.submission_deadline_passed'));
        abort_if(
            $assignment->submissions()->where('user_id', $user->user_id)->exists(),
            422,
            __('pages.submission_already_exists')
        );

        $validated = $request->validate([
            'submission_content' => 'required|string',
            'file' => 'required|file|mimes:pdf|max:'.Assignment::MAX_UPLOAD_KB,
        ]);

        $path = $request->file('file')->store('submissions', 'public');

        $submission = $assignment->submissions()->create([
            'user_id' => $user->user_id,
            'submission_content' => $validated['submission_content'],
            'file_path' => $path,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'submission_id' => $submission->submission_id ?? $submission->getKey(),
                'submitted_at' => now()->toIso8601String(),
                'file_url' => Storage::disk('public')->url($path),
            ],
        ], 201);
    }

    public function updateSubmission(Request $request, AssignmentSubmission $submission): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $assignment = $submission->assignment;
        abort_unless($assignment, 404);
        $course = Course::findOrFail($assignment->course_id);
        $this->authorizeCoursePermission($user, $course, 'assignment.submit');
        abort_unless($user->isStudent() && (int) $submission->user_id === (int) $user->user_id, 403);
        abort_if($assignment->isOffline(), 422, __('pages.assignment_offline_no_upload'));
        abort_unless($assignment->isSubmissionOpen(), 422, __('pages.submission_deadline_passed'));

        $validated = $request->validate([
            'submission_content' => 'required|string',
            'file' => 'nullable|file|mimes:pdf|max:'.Assignment::MAX_UPLOAD_KB,
        ]);

        $submission->submission_content = $validated['submission_content'];
        if ($request->hasFile('file')) {
            if ($submission->file_path) {
                Storage::disk('public')->delete($submission->file_path);
            }
            $submission->file_path = $request->file('file')->store('submissions', 'public');
        }
        $submission->submitted_at = now();
        $submission->save();

        return response()->json([
            'data' => [
                'submission_id' => $submission->submission_id ?? $submission->getKey(),
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'file_url' => $submission->file_path
                    ? Storage::disk('public')->url($submission->file_path)
                    : null,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(Assignment $assignment, User $user, bool $detailed = false): array
    {
        $submission = $assignment->submissions()
            ->where('user_id', $user->user_id)
            ->first();

        $payload = [
            'assignment_id' => $assignment->assignment_id,
            'course_id' => $assignment->course_id,
            'assignment_name' => $assignment->assignment_name,
            'delivery_mode' => $assignment->delivery_mode ?? Assignment::MODE_ONLINE,
            'due_date' => $assignment->due_date?->toIso8601String(),
            'total_points' => $assignment->total_points,
            'submission_open' => $assignment->isOnline() && $assignment->isSubmissionOpen(),
            'has_submission' => (bool) $submission,
        ];

        if ($detailed) {
            $payload['assignment_description'] = $assignment->assignment_description;
            $payload['instructions'] = $assignment->instructions;
            $payload['submission'] = $submission ? [
                'submission_id' => $submission->submission_id ?? $submission->getKey(),
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'points_earned' => $submission->points_earned ?? null,
                'feedback' => $submission->feedback ?? null,
            ] : null;
        }

        return $payload;
    }
}
