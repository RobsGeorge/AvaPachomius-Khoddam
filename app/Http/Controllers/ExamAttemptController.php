<?php

namespace App\Http\Controllers;

use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Services\ExamGradingService;
use App\Services\ExamProctorService;
use App\Services\ExamTimerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ExamAttemptController extends Controller
{
    public function __construct(
        private ExamTimerService $timer,
        private ExamGradingService $grading,
        private ExamProctorService $proctor,
    ) {}

    /** Redirect to pre-exam lobby (one entry point for students). */
    public function start(ExamSchedule $schedule)
    {
        return redirect()->route('exams.attempt.lobby', $schedule);
    }

    public function lobby(ExamSchedule $schedule)
    {
        $schedule->load(['exam.questions', 'exam.module']);
        $exam = $schedule->exam;

        abort_unless($exam && $exam->isOnline() && $exam->is_published, 403);

        $attempt = ExamAttempt::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->first();

        $result = ExamResult::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->first();

        if ($result && $result->isDone()) {
            return redirect()
                ->route('exams.attempt.confirmation', $schedule)
                ->with('info', __('exams.already_submitted'));
        }

        if ($attempt && $attempt->hasStartedAttempt() && ! $attempt->isSubmitted()) {
            return redirect()->route('exams.attempt.show', $schedule);
        }

        $timer = $this->timer->state($schedule);
        $canEnter = $this->timer->canEnter($schedule);

        return view('exams.lobby', compact('schedule', 'exam', 'timer', 'canEnter', 'attempt'));
    }

    public function begin(Request $request, ExamSchedule $schedule)
    {
        $schedule->load('exam.questions');
        $exam = $schedule->exam;

        abort_unless($exam && $exam->isOnline() && $exam->is_published, 403);
        abort_unless($this->timer->canEnter($schedule), 403, __('exams.exam_not_available'));

        $request->validate([
            'acknowledge_rules'     => 'accepted',
            'acknowledge_timer'     => 'accepted',
            'acknowledge_proctor'   => 'accepted',
            'acknowledge_one_attempt' => 'accepted',
        ]);

        $existingResult = ExamResult::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existingResult && $existingResult->isDone()) {
            return redirect()
                ->route('exams.attempt.confirmation', $schedule)
                ->with('error', __('exams.one_attempt_only'));
        }

        $attempt = ExamAttempt::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->first();

        if ($attempt && $attempt->hasStartedAttempt()) {
            if ($attempt->isSubmitted()) {
                return redirect()
                    ->route('exams.attempt.confirmation', $schedule)
                    ->with('error', __('exams.one_attempt_only'));
            }

            return redirect()->route('exams.attempt.show', $schedule);
        }

        if ($attempt && ! $attempt->hasStartedAttempt()) {
            $attempt->update([
                'checklist_acknowledged_at' => now(),
                'started_at'                => now(),
                'status'                    => ExamAttempt::STATUS_IN_PROGRESS,
            ]);
        } else {
            ExamAttempt::create([
                'exam_id'                   => $exam->exam_id,
                'schedule_id'               => $schedule->schedule_id,
                'user_id'                   => Auth::id(),
                'status'                    => ExamAttempt::STATUS_IN_PROGRESS,
                'started_at'                => now(),
                'checklist_acknowledged_at' => now(),
                'answers_json'              => [],
            ]);
        }

        return redirect()
            ->route('exams.attempt.show', $schedule)
            ->with('success', __('exams.exam_started'));
    }

    public function show(ExamSchedule $schedule)
    {
        $schedule->load(['exam.questions.options']);
        $exam = $schedule->exam;

        abort_unless($exam && $exam->isOnline(), 404);

        $attempt = ExamAttempt::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        abort_unless($attempt->hasStartedAttempt(), 403, __('exams.complete_checklist_first'));

        if ($attempt->isSubmitted() || $attempt->isTerminatedForCheating()) {
            return redirect()->route('exams.attempt.confirmation', $schedule);
        }

        $timer = $this->timer->state($schedule);

        if ($timer['has_ended']) {
            $this->grading->submitAttempt($attempt);

            return redirect()->route('exams.attempt.confirmation', $schedule);
        }

        $questions = $exam->questions;
        if ($exam->shuffle_questions) {
            $questions = $this->shuffleQuestionsForAttempt($questions, $attempt);
        }

        $saved = $attempt->answers_json ?? [];

        return view('exams.take', compact('schedule', 'exam', 'attempt', 'questions', 'timer', 'saved'));
    }

    public function save(Request $request, ExamSchedule $schedule): JsonResponse
    {
        $attempt = $this->activeAttempt($schedule);

        $timer = $this->timer->state($schedule);
        if ($timer['has_ended']) {
            $this->grading->submitAttempt($attempt);

            return response()->json([
                'saved'     => false,
                'submitted' => true,
                'redirect'  => route('exams.attempt.confirmation', $schedule),
            ]);
        }

        $answers = $request->input('answers', []);
        if (! is_array($answers)) {
            $answers = [];
        }

        $this->grading->persistAnswers($attempt, $answers);
        $attempt->update(['answers_json' => $answers]);

        return response()->json([
            'saved'    => true,
            'saved_at' => now()->toIso8601String(),
            'timer'    => $timer,
        ]);
    }

    public function submit(Request $request, ExamSchedule $schedule)
    {
        $attempt = ExamAttempt::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($attempt->isSubmitted()) {
            return redirect()->route('exams.attempt.confirmation', $schedule);
        }

        if ($attempt->isTerminatedForCheating()) {
            return redirect()->route('exams.attempt.confirmation', $schedule);
        }

        $answers = $request->input('answers', []);
        if (is_array($answers)) {
            $attempt->update(['answers_json' => $answers]);
        }

        $this->grading->submitAttempt($attempt);

        return redirect()
            ->route('exams.attempt.confirmation', $schedule)
            ->with('success', __('exams.submit_success'));
    }

    public function proctor(Request $request, ExamSchedule $schedule): JsonResponse
    {
        $attempt = ExamAttempt::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (! $attempt->hasStartedAttempt() || $attempt->isSubmitted()) {
            return response()->json(['action' => 'ignored'], 409);
        }

        $data = $request->validate([
            'event_type' => 'required|string|in:tab_hidden,window_blur,page_hide',
            'details'    => 'nullable|string|max:500',
        ]);

        $result = $this->proctor->recordViolation(
            $attempt,
            $data['event_type'],
            $data['details'] ?? null
        );

        return response()->json($result);
    }

    public function timer(ExamSchedule $schedule): JsonResponse
    {
        return response()->json($this->timer->state($schedule));
    }

    public function confirmation(ExamSchedule $schedule)
    {
        $schedule->load('exam.module', 'exam.course');
        $result = ExamResult::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->first();

        $attempt = ExamAttempt::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->first();

        return view('exams.confirmation', compact('schedule', 'result', 'attempt'));
    }

    private function activeAttempt(ExamSchedule $schedule): ExamAttempt
    {
        $attempt = ExamAttempt::where('schedule_id', $schedule->schedule_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        abort_if($attempt->isSubmitted() || $attempt->isTerminatedForCheating(), 409, __('exams.already_submitted'));
        abort_unless($attempt->hasStartedAttempt(), 403);

        return $attempt;
    }

    /**
     * Deterministic shuffle so question order stays stable for one attempt across reloads.
     *
     * @param  Collection<int, \App\Models\ExamQuestion>  $questions
     * @return Collection<int, \App\Models\ExamQuestion>
     */
    private function shuffleQuestionsForAttempt(Collection $questions, ExamAttempt $attempt): Collection
    {
        $items = $questions->values()->all();
        $n = count($items);

        if ($n <= 1) {
            return collect($items);
        }

        $seed = (int) $attempt->attempt_id;
        for ($i = $n - 1; $i > 0; $i--) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $j = $seed % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return collect($items);
    }
}
