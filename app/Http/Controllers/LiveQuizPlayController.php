<?php

namespace App\Http\Controllers;

use App\Models\LiveQuizSession;
use App\Models\LiveQuizQuestion;
use App\Services\LiveQuizSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiveQuizPlayController extends Controller
{
    public function __construct(
        private LiveQuizSessionService $sessions
    ) {}

    public function joinForm()
    {
        return view('live-quiz.play.join');
    }

    public function join(Request $request)
    {
        $data = $request->validate([
            'join_code' => 'required|string|size:6',
            'team_number' => 'nullable|integer|min:1|max:20',
        ]);

        $session = LiveQuizSession::where('join_code', strtoupper($data['join_code']))
            ->where('status', '!=', LiveQuizSession::STATUS_ENDED)
            ->latest('session_id')
            ->firstOrFail();

        $user = Auth::user();
        $displayName = trim($user->first_name.' '.$user->second_name) ?: ($user->name ?? __('dashboard.user_fallback'));

        $participant = $this->sessions->joinSession(
            $session,
            $user->user_id,
            $displayName,
            isset($data['team_number']) ? (int) $data['team_number'] : null
        );

        return redirect()->route('live-quiz.play.lobby', $session);
    }

    public function lobby(LiveQuizSession $session)
    {
        $participant = $this->participantForSession($session);
        $session->load(['quiz.questions', 'participants']);

        return view('live-quiz.play.lobby', compact('session', 'participant'));
    }

    public function play(LiveQuizSession $session)
    {
        $participant = $this->participantForSession($session);
        $session->load(['quiz.questions.options', 'participants']);

        return view('live-quiz.play.play', compact('session', 'participant'));
    }

    public function answer(Request $request, LiveQuizSession $session, LiveQuizQuestion $question)
    {
        $participant = $this->participantForSession($session);

        $data = $request->validate([
            'option_id' => 'required|integer',
        ]);

        $this->sessions->submitAnswer($session, $participant, $question, (int) $data['option_id']);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    private function participantForSession(LiveQuizSession $session)
    {
        $participant = $session->participants()
            ->where('user_id', Auth::user()->user_id)
            ->first();

        abort_unless($participant, 403);

        return $participant;
    }
}
