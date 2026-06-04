<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExamBuilderController extends Controller
{
    public function edit(Exam $exam)
    {
        $exam->load(['course', 'module', 'questions.options']);

        return view('exams.builder', compact('exam'));
    }

    public function storeQuestion(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'question_type'   => ['required', Rule::in([
                ExamQuestion::TYPE_MCQ,
                ExamQuestion::TYPE_TRUE_FALSE,
                ExamQuestion::TYPE_ESSAY,
            ])],
            'prompt'          => 'required|string|max:5000',
            'points'          => 'required|numeric|min:0.25|max:9999',
            'essay_ai_prompt' => 'nullable|string|max:5000',
            'essay_keywords'  => 'nullable|string|max:2000',
            'essay_rubric'    => 'nullable|string|max:5000',
            'options'         => 'nullable|array',
            'options.*.label' => 'nullable|string|max:500',
            'options.*.is_correct' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($exam, $data) {
            $order = ($exam->questions()->max('order_index') ?? 0) + 1;

            $question = $exam->questions()->create([
                'question_type'   => $data['question_type'],
                'prompt'          => $data['prompt'],
                'points'          => $data['points'],
                'order_index'     => $order,
                'essay_ai_prompt' => $data['essay_ai_prompt'] ?? null,
                'essay_keywords'  => $data['essay_keywords'] ?? null,
                'essay_rubric'    => $data['essay_rubric'] ?? null,
            ]);

            $this->syncOptions($question, $data);
            $exam->recalculateTotalPoints();
        });

        return redirect()
            ->route('exams.builder', $exam)
            ->with('success', __('exams.question_added'));
    }

    public function updateQuestion(Request $request, Exam $exam, ExamQuestion $question)
    {
        abort_unless((int) $question->exam_id === (int) $exam->exam_id, 404);

        $data = $request->validate([
            'prompt'          => 'required|string|max:5000',
            'points'          => 'required|numeric|min:0.25|max:9999',
            'essay_ai_prompt' => 'nullable|string|max:5000',
            'essay_keywords'  => 'nullable|string|max:2000',
            'essay_rubric'    => 'nullable|string|max:5000',
            'options'         => 'nullable|array',
            'options.*.label' => 'nullable|string|max:500',
            'options.*.is_correct' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($exam, $question, $data) {
            $question->update([
                'prompt'          => $data['prompt'],
                'points'          => $data['points'],
                'essay_ai_prompt' => $data['essay_ai_prompt'] ?? null,
                'essay_keywords'  => $data['essay_keywords'] ?? null,
                'essay_rubric'    => $data['essay_rubric'] ?? null,
            ]);

            $this->syncOptions($question, $data);
            $exam->recalculateTotalPoints();
        });

        return redirect()
            ->route('exams.builder', $exam)
            ->with('success', __('exams.question_updated'));
    }

    public function destroyQuestion(Exam $exam, ExamQuestion $question)
    {
        abort_unless((int) $question->exam_id === (int) $exam->exam_id, 404);

        $question->delete();
        $exam->recalculateTotalPoints();

        return redirect()
            ->route('exams.builder', $exam)
            ->with('success', __('exams.question_deleted'));
    }

    public function publish(Exam $exam)
    {
        if ($exam->isOnline() && $exam->questions()->count() === 0) {
            return back()->withErrors(['publish' => __('exams.publish_requires_questions')]);
        }

        $exam->update(['is_published' => true]);

        return back()->with('success', __('exams.exam_published'));
    }

    /** @param array<string, mixed> $data */
    private function syncOptions(ExamQuestion $question, array $data): void
    {
        if ($question->question_type === ExamQuestion::TYPE_ESSAY) {
            $question->options()->delete();

            return;
        }

        if ($question->question_type === ExamQuestion::TYPE_TRUE_FALSE) {
            $question->options()->delete();
            $correctTrue = (bool) data_get($data, 'options.0.is_correct');
            $correctFalse = (bool) data_get($data, 'options.1.is_correct');
            if ($correctTrue && $correctFalse) {
                $correctFalse = false;
            }
            if (! $correctTrue && ! $correctFalse) {
                $correctTrue = true;
            }
            $question->options()->createMany([
                ['label' => __('exams.true_label'), 'is_correct' => $correctTrue, 'order_index' => 0],
                ['label' => __('exams.false_label'), 'is_correct' => $correctFalse, 'order_index' => 1],
            ]);

            return;
        }

        $question->options()->delete();
        $options = $data['options'] ?? [];
        $order = 0;
        $hasCorrect = false;

        foreach ($options as $opt) {
            $label = trim((string) ($opt['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $isCorrect = (bool) ($opt['is_correct'] ?? false);
            if ($isCorrect && $hasCorrect) {
                $isCorrect = false;
            }
            if ($isCorrect) {
                $hasCorrect = true;
            }
            $question->options()->create([
                'label'       => $label,
                'is_correct'  => $isCorrect,
                'order_index' => $order++,
            ]);
        }

        if ($order > 0 && ! $hasCorrect) {
            $question->options()->first()?->update(['is_correct' => true]);
        }
    }
}
