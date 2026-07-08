<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LiveQuiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LiveQuizController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->isInstructorOrAdmin()) {
            $quizzes = LiveQuiz::with('course')
                ->where('created_by_user_id', $user->user_id)
                ->latest('live_quiz_id')
                ->paginate(20);
        } else {
            $quizzes = collect();
        }

        return view('live-quiz.index', compact('quizzes'));
    }

    public function create()
    {
        $courses = Course::orderBy('title')->get(['course_id', 'title']);

        return view('live-quiz.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'course_id' => 'nullable|exists:course,course_id',
            'mode' => ['required', Rule::in([LiveQuiz::MODE_INDIVIDUAL, LiveQuiz::MODE_TEAM])],
            'team_count' => 'nullable|integer|min:2|max:20',
        ]);

        if ($data['mode'] === LiveQuiz::MODE_TEAM && empty($data['team_count'])) {
            return back()->withErrors(['team_count' => __('pages.live_quiz_team_count_required')])->withInput();
        }

        $quiz = LiveQuiz::create([
            'title' => $data['title'],
            'course_id' => $data['course_id'] ?? null,
            'created_by_user_id' => Auth::user()->user_id,
            'mode' => $data['mode'],
            'team_count' => $data['mode'] === LiveQuiz::MODE_TEAM ? $data['team_count'] : null,
            'status' => LiveQuiz::STATUS_DRAFT,
        ]);

        return redirect()
            ->route('live-quiz.builder', $quiz)
            ->with('success', __('pages.live_quiz_created'));
    }

    public function show(LiveQuiz $liveQuiz)
    {
        $this->authorizeQuiz($liveQuiz);
        $liveQuiz->load(['questions.options', 'course']);

        return view('live-quiz.show', compact('liveQuiz'));
    }

    public function destroy(LiveQuiz $liveQuiz)
    {
        $this->authorizeQuiz($liveQuiz);
        $liveQuiz->delete();

        return redirect()
            ->route('live-quiz.index')
            ->with('success', __('pages.live_quiz_deleted'));
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
