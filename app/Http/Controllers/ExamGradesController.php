<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Services\ExamGradingService;
use App\Services\ExamProctorService;
use Illuminate\Http\Request;

class ExamGradesController extends Controller
{
    public function __construct(
        private ExamGradingService $grading,
        private ExamProctorService $proctor,
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
        ]);

        return view('exams.grades', compact('exam'));
    }

    public function storeOffline(Request $request, Exam $exam)
    {
        abort_unless($exam->isOffline(), 403);

        $data = $request->validate([
            'schedule_id' => 'required|exists:exam_schedules,schedule_id',
            'user_id'     => 'required|exists:user,user_id',
            'score'       => 'required|numeric|min:0|max:100',
        ]);

        $result = ExamResult::updateOrCreate(
            [
                'exam_id'     => $exam->exam_id,
                'schedule_id' => $data['schedule_id'],
                'user_id'     => $data['user_id'],
            ],
            ['score' => $data['score']]
        );

        $this->grading->saveOfflineScore($result, (float) $data['score']);

        return back()->with('success', __('exams.grade_saved'));
    }

    public function updateManual(Request $request, Exam $exam, ExamResult $result)
    {
        abort_unless((int) $result->exam_id === (int) $exam->exam_id, 404);

        if ($exam->isOffline()) {
            $data = $request->validate(['score' => 'required|numeric|min:0|max:100']);
            $this->grading->saveOfflineScore($result, (float) $data['score']);

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
}
