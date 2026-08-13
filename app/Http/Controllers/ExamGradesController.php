<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Services\AuditLogService;
use App\Services\ExamGradingService;
use App\Services\ExamProctorService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExamGradesController extends Controller
{
    public function __construct(
        private ExamGradingService $grading,
        private ExamProctorService $proctor,
        private StudentRosterService $roster,
    ) {}

    public function show(Exam $exam)
    {
        $exam->load([
            'course',
            'module',
            'schedules.results.user',
            'schedules.results.attempt.proctorEvents',
            'schedules.results.attempt.answers.question',
            'schedules.attempts.answers.question',
            'questions',
            'resultsAnnouncer',
        ]);

        $students = $exam->course_id
            ? $this->roster->enrolledStudents($exam->course_id)
            : collect();

        return view('exams.grades', compact('exam', 'students'));
    }

    public function announce(Exam $exam)
    {
        abort_if($exam->areResultsAnnounced(), 409, __('exams.results_already_announced'));

        $actor = Auth::user();

        $exam->update([
            'results_announced_at' => now(),
            'results_announced_by_user_id' => $actor?->user_id,
        ]);

        AuditLogService::recordEvent('exam.results_announced', [
            'actor_user_id' => $actor?->user_id,
            'exam_id' => $exam->exam_id,
            'course_id' => $exam->course_id,
            'module_id' => $exam->module_id,
        ]);

        return back()->with('success', __('exams.results_announced'));
    }

    public function updateTotalPoints(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'total_points' => 'required|numeric|min:0.01|max:9999.99',
        ], [
            'total_points.required' => __('exams.total_points_required'),
        ]);

        $exam->update(['total_points' => $data['total_points']]);

        return back()->with('success', __('exams.total_points_saved'));
    }

    public function storeOffline(Request $request, Exam $exam)
    {
        abort_unless($exam->isOffline(), 403);
        $this->assertExamHasTotalPoints($exam);

        $maxPoints = (float) $exam->total_points;

        $data = $request->validate([
            'schedule_id' => 'required|exists:exam_schedules,schedule_id',
            'user_id'     => 'required|exists:user,user_id',
            'points'      => "required|numeric|min:0|max:{$maxPoints}",
        ]);

        $this->assertScheduleBelongsToExam($exam, (int) $data['schedule_id']);
        $this->assertStudentAllowed($exam, (int) $data['user_id'], (int) $data['schedule_id']);

        $percent = $this->grading->pointsToPercent($exam, (float) $data['points']);

        $result = ExamResult::updateOrCreate(
            [
                'exam_id'     => $exam->exam_id,
                'schedule_id' => $data['schedule_id'],
                'user_id'     => $data['user_id'],
            ],
            ['score' => $percent]
        );

        $this->grading->saveOfflineScore($result, $percent);

        return back()->with('success', __('exams.grade_saved'));
    }

    public function storeOfflineBulk(Request $request, Exam $exam)
    {
        abort_unless($exam->isOffline(), 403);
        $this->assertExamHasTotalPoints($exam);

        $maxPoints = (float) $exam->total_points;

        $data = $request->validate([
            'schedule_id' => 'required|exists:exam_schedules,schedule_id',
            'points'      => 'nullable|array',
            'points.*'    => "nullable|numeric|min:0|max:{$maxPoints}",
        ]);

        $this->assertScheduleBelongsToExam($exam, (int) $data['schedule_id']);

        $enrolledIds = $this->enrolledUserIds($exam);
        $points = $data['points'] ?? [];

        foreach ($points as $userId => $rawPoints) {
            $userId = (int) $userId;

            if ($rawPoints === null || $rawPoints === '') {
                continue;
            }

            if (! $enrolledIds->contains($userId)) {
                throw ValidationException::withMessages([
                    "points.{$userId}" => __('exams.student_not_enrolled'),
                ]);
            }

            $percent = $this->grading->pointsToPercent($exam, (float) $rawPoints);

            $result = ExamResult::updateOrCreate(
                [
                    'exam_id'     => $exam->exam_id,
                    'schedule_id' => $data['schedule_id'],
                    'user_id'     => $userId,
                ],
                ['score' => $percent]
            );

            $this->grading->saveOfflineScore($result, $percent);
        }

        return back()->with('success', __('exams.grades_saved_bulk'));
    }

    public function updateManual(Request $request, Exam $exam, ExamResult $result)
    {
        abort_unless((int) $result->exam_id === (int) $exam->exam_id, 404);

        if ($exam->isOffline()) {
            $this->assertExamHasTotalPoints($exam);
            $maxPoints = (float) $exam->total_points;

            $data = $request->validate([
                'points' => "required|numeric|min:0|max:{$maxPoints}",
            ]);

            $percent = $this->grading->pointsToPercent($exam, (float) $data['points']);
            $this->grading->saveOfflineScore($result, $percent);

            return back()->with('success', __('exams.grade_saved'));
        }

        $data = $request->validate([
            'scores'   => 'nullable|array',
            'scores.*' => 'nullable|numeric|min:0|max:9999',
            'score'    => 'nullable|numeric|min:0|max:100',
        ]);

        if (! empty($data['scores'])) {
            $this->grading->updateManualScores($result, $data['scores']);
        } elseif (isset($data['score'])) {
            $result->update([
                'score'        => $data['score'],
                'manual_score' => $data['score'],
                'status'       => ExamResult::STATUS_GRADED,
            ]);
        }

        return back()->with('success', __('exams.grade_saved'));
    }

    public function clearCheater(Request $request, Exam $exam, ExamResult $result)
    {
        abort_unless((int) $result->exam_id === (int) $exam->exam_id, 404);
        abort_unless($result->isCheater(), 403);

        $data = $request->validate([
            'score' => 'nullable|numeric|min:0|max:100',
        ]);

        $this->proctor->clearCheaterFlag(
            $result,
            isset($data['score']) ? (float) $data['score'] : null
        );

        return back()->with('success', __('exams.cheater_flag_cleared'));
    }

    private function assertExamHasTotalPoints(Exam $exam): void
    {
        if ((float) $exam->total_points <= 0) {
            throw ValidationException::withMessages([
                'points' => __('exams.total_points_required'),
            ]);
        }
    }

    private function assertScheduleBelongsToExam(Exam $exam, int $scheduleId): void
    {
        $belongs = ExamSchedule::query()
            ->where('schedule_id', $scheduleId)
            ->where('exam_id', $exam->exam_id)
            ->exists();

        abort_unless($belongs, 404);
    }

    private function assertStudentAllowed(Exam $exam, int $userId, int $scheduleId): void
    {
        $enrolled = $this->enrolledUserIds($exam)->contains($userId);
        $hasResult = ExamResult::query()
            ->where('exam_id', $exam->exam_id)
            ->where('schedule_id', $scheduleId)
            ->where('user_id', $userId)
            ->exists();

        if (! $enrolled && ! $hasResult) {
            throw ValidationException::withMessages([
                'user_id' => __('exams.student_not_enrolled'),
            ]);
        }
    }

    private function enrolledUserIds(Exam $exam): Collection
    {
        if (! $exam->course_id) {
            return collect();
        }

        return $this->roster->enrolledStudents($exam->course_id)->pluck('user_id')->map(fn ($id) => (int) $id);
    }
}
