<?php

namespace App\Http\Controllers;

use App\Models\LiveQuiz;
use App\Models\LiveQuizSession;
use App\Services\LiveQuizSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiveQuizHostController extends Controller
{
    public function __construct(
        private LiveQuizSessionService $sessions
    ) {}

    public function start(LiveQuiz $liveQuiz)
    {
        $this->authorizeHost($liveQuiz);

        $session = $this->sessions->startSession($liveQuiz, Auth::user()->user_id);

        return redirect()->route('live-quiz.host.lobby', $session);
    }

    public function lobby(LiveQuizSession $session)
    {
        $this->authorizeSessionHost($session);
        $session->load(['quiz.questions', 'participants']);

        return view('live-quiz.host.lobby', compact('session'));
    }

    public function launchQuestion(Request $request, LiveQuizSession $session)
    {
        $this->authorizeSessionHost($session);

        $data = $request->validate([
            'order_index' => 'required|integer|min:1',
        ]);

        $this->sessions->launchQuestion($session, (int) $data['order_index']);

        return redirect()->route('live-quiz.host.control', $session);
    }

    public function control(LiveQuizSession $session)
    {
        $this->authorizeSessionHost($session);
        $session->load(['quiz.questions.options', 'participants', 'answers']);

        return view('live-quiz.host.control', compact('session'));
    }

    public function showResults(LiveQuizSession $session)
    {
        $this->authorizeSessionHost($session);
        $this->sessions->showResults($session);

        return redirect()->route('live-quiz.host.control', $session);
    }

    public function end(LiveQuizSession $session)
    {
        $this->authorizeSessionHost($session);
        $this->sessions->endSession($session);

        return redirect()->route('live-quiz.host.control', $session)
            ->with('success', __('pages.live_quiz_session_ended_success'));
    }

    public function present(LiveQuizSession $session)
    {
        $this->authorizeSessionHost($session);
        $session->load(['quiz.questions.options', 'participants', 'answers']);

        return view('live-quiz.host.present', compact('session'));
    }

    private function authorizeHost(LiveQuiz $quiz): void
    {
        abort_unless(
            Auth::user()->isInstructorOrAdmin()
            && (int) $quiz->created_by_user_id === (int) Auth::user()->user_id,
            403
        );
    }

    private function authorizeSessionHost(LiveQuizSession $session): void
    {
        abort_unless(
            Auth::user()->isInstructorOrAdmin()
            && (int) $session->host_user_id === (int) Auth::user()->user_id,
            403
        );
    }
}
