<?php

namespace App\Services;

use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EssayGradingService
{
    /**
     * @return array{score: float, feedback: string, needs_review: bool}
     */
    public function grade(ExamAnswer $answer, ExamQuestion $question): array
    {
        $text = trim((string) $answer->text_answer);
        $maxPoints = (float) $question->points;

        if ($text === '') {
            return [
                'score'         => 0.0,
                'feedback'      => __('exams.essay_empty'),
                'needs_review'  => false,
            ];
        }

        $driver = config('exams.essay_grading_driver', 'openai');
        $apiKey = config('exams.openai.api_key');

        if ($driver === 'openai' && $apiKey) {
            $ai = $this->gradeViaOpenAi($text, $question, $maxPoints);
            if ($ai !== null) {
                return $ai;
            }
        }

        return $this->gradeViaKeywords($text, $question, $maxPoints);
    }

    /**
     * @return array{score: float, feedback: string, needs_review: bool}|null
     */
    private function gradeViaOpenAi(string $text, ExamQuestion $question, float $maxPoints): ?array
    {
        $keywords = trim((string) $question->essay_keywords);
        $rubric = trim((string) $question->essay_rubric);
        $instructorPrompt = trim((string) $question->essay_ai_prompt);

        $system = implode("\n", array_filter([
            'You are an exam grader. Score the student answer from 0 to '.$maxPoints.'.',
            'Respond ONLY with valid JSON: {"score": number, "feedback": "string", "needs_review": boolean}',
            $instructorPrompt !== '' ? 'Instructor instructions: '.$instructorPrompt : null,
            $rubric !== '' ? 'Rubric: '.$rubric : null,
            $keywords !== '' ? 'Expected keywords/concepts: '.$keywords : null,
        ]));

        try {
            $response = Http::timeout(30)
                ->withToken(config('exams.openai.api_key'))
                ->post(rtrim(config('exams.openai.base_url'), '/').'/chat/completions', [
                    'model'       => config('exams.openai.model'),
                    'temperature' => 0.2,
                    'messages'    => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => "Question:\n{$question->prompt}\n\nStudent answer:\n{$text}"],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Essay AI grading failed', ['status' => $response->status()]);

                return null;
            }

            $content = data_get($response->json(), 'choices.0.message.content');
            if (! is_string($content)) {
                return null;
            }

            $json = $this->extractJson($content);
            if (! is_array($json)) {
                return null;
            }

            $score = min($maxPoints, max(0, (float) ($json['score'] ?? 0)));

            return [
                'score'        => $score,
                'feedback'     => (string) ($json['feedback'] ?? ''),
                'needs_review' => (bool) ($json['needs_review'] ?? true),
            ];
        } catch (\Throwable $e) {
            Log::warning('Essay AI grading exception: '.$e->getMessage());

            return null;
        }
    }

    /** @return array{score: float, feedback: string, needs_review: bool} */
    private function gradeViaKeywords(string $text, ExamQuestion $question, float $maxPoints): array
    {
        $keywords = array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) $question->essay_keywords) ?: []));
        $haystack = mb_strtolower($text);
        $matched = [];
        $missed = [];

        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }
            if (mb_strpos($haystack, mb_strtolower($keyword)) !== false) {
                $matched[] = $keyword;
            } else {
                $missed[] = $keyword;
            }
        }

        $ratio = $keywords === [] ? 0.5 : count($matched) / max(1, count($keywords));
        $score = round($maxPoints * $ratio, 2);

        $feedback = $keywords === []
            ? __('exams.essay_keyword_fallback')
            : __('exams.essay_keyword_result', [
                'matched' => implode(', ', $matched) ?: '—',
                'missed'  => implode(', ', $missed) ?: '—',
            ]);

        return [
            'score'        => $score,
            'feedback'     => $feedback,
            'needs_review' => true,
        ];
    }

    /** @return array<string, mixed>|null */
    private function extractJson(string $content): ?array
    {
        $content = trim($content);
        if (str_starts_with($content, '{')) {
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : null;
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
