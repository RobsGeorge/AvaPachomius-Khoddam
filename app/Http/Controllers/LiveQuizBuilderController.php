<?php

namespace App\Http\Controllers;

use App\Models\LiveQuiz;
use App\Models\LiveQuizOption;
use App\Models\LiveQuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LiveQuizBuilderController extends Controller
{
    public function edit(LiveQuiz $liveQuiz)
    {
        $this->authorizeQuiz($liveQuiz);
        $liveQuiz->load(['questions.options', 'course']);

        return view('live-quiz.builder', compact('liveQuiz'));
    }

    public function storeQuestion(Request $request, LiveQuiz $liveQuiz)
    {
        $this->authorizeQuiz($liveQuiz);

        $data = $request->validate([
            'question_type' => ['required', Rule::in([
                LiveQuizQuestion::TYPE_MCQ,
                LiveQuizQuestion::TYPE_TRUE_FALSE,
            ])],
            'prompt_text' => 'nullable|string|max:5000',
            'prompt_image' => 'nullable|image|max:5120',
            'time_limit_seconds' => 'required|integer|min:5|max:300',
            'points' => 'required|numeric|min:0.25|max:9999',
            'options' => 'nullable|array',
            'options.*.label' => 'nullable|string|max:500',
            'options.*.is_correct' => 'nullable|boolean',
            'option_images' => 'nullable|array',
            'option_images.*' => 'nullable|image|max:5120',
        ]);

        if (empty($data['prompt_text']) && ! $request->hasFile('prompt_image')) {
            return back()->withErrors(['prompt_text' => __('pages.live_quiz_prompt_required')])->withInput();
        }

        DB::transaction(function () use ($liveQuiz, $data, $request) {
            $order = ($liveQuiz->questions()->max('order_index') ?? 0) + 1;
            $promptImage = $request->file('prompt_image')
                ? $request->file('prompt_image')->store('live-quiz', 'public')
                : null;

            $question = $liveQuiz->questions()->create([
                'order_index' => $order,
                'question_type' => $data['question_type'],
                'prompt_text' => $data['prompt_text'] ?? null,
                'prompt_image_path' => $promptImage,
                'time_limit_seconds' => $data['time_limit_seconds'],
                'points' => $data['points'],
            ]);

            $this->syncOptions($question, $data, $request);
            $liveQuiz->update(['status' => LiveQuiz::STATUS_READY]);
        });

        return redirect()
            ->route('live-quiz.builder', $liveQuiz)
            ->with('success', __('pages.live_quiz_question_added'));
    }

    public function destroyQuestion(LiveQuiz $liveQuiz, LiveQuizQuestion $question)
    {
        $this->authorizeQuiz($liveQuiz);
        abort_unless((int) $question->live_quiz_id === (int) $liveQuiz->live_quiz_id, 404);

        if ($question->prompt_image_path) {
            Storage::disk('public')->delete($question->prompt_image_path);
        }
        foreach ($question->options as $option) {
            if ($option->label_image_path) {
                Storage::disk('public')->delete($option->label_image_path);
            }
        }

        $question->delete();

        return redirect()
            ->route('live-quiz.builder', $liveQuiz)
            ->with('success', __('pages.live_quiz_question_deleted'));
    }

    private function syncOptions(LiveQuizQuestion $question, array $data, Request $request): void
    {
        $question->options()->delete();

        if ($question->question_type === LiveQuizQuestion::TYPE_TRUE_FALSE) {
            $question->options()->createMany([
                ['label_text' => __('pages.true'), 'is_correct' => (bool) ($data['options'][0]['is_correct'] ?? false), 'order_index' => 1],
                ['label_text' => __('pages.false'), 'is_correct' => (bool) ($data['options'][1]['is_correct'] ?? false), 'order_index' => 2],
            ]);

            return;
        }

        $options = $data['options'] ?? [];
        $order = 1;
        foreach ($options as $index => $optionData) {
            $label = trim($optionData['label'] ?? '');
            $imagePath = null;
            if ($request->hasFile("option_images.$index")) {
                $imagePath = $request->file("option_images.$index")->store('live-quiz', 'public');
            }

            if ($label === '' && ! $imagePath) {
                continue;
            }

            $question->options()->create([
                'label_text' => $label !== '' ? $label : null,
                'label_image_path' => $imagePath,
                'is_correct' => (bool) ($optionData['is_correct'] ?? false),
                'order_index' => $order++,
            ]);
        }
    }

    private function authorizeQuiz(LiveQuiz $quiz): void
    {
        abort_unless(
            Auth::user()->isInstructorOrAdmin()
            && (int) $quiz->created_by_user_id === (int) Auth::user()->user_id,
            403
        );
    }
}
