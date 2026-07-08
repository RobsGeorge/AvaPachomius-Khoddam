<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LiveFeedbackSession;
use App\Models\ModuleFeedback;
use App\Services\LiveFeedbackSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiveFeedbackPlayController extends Controller
{
    public function __construct(
        private LiveFeedbackSessionService $sessions
    ) {}

    public function play(string $courseId, string $moduleId)
    {
        $course = Course::findOrFail($courseId);
        $module = $course->modules()->where('modules.module_id', $moduleId)->firstOrFail();

        if (! (bool) ($module->pivot->feedback_open ?? false)) {
            return redirect()->route('curriculum.show', $courseId)
                ->with('warning', __('pages.module_feedback_not_open'));
        }

        $session = $this->sessions->activeSessionForModule((int) $courseId, (int) $moduleId);

        if (! $session) {
            return redirect()->route('module-feedback.show', [$courseId, $moduleId]);
        }

        $response = $session->responses()
            ->where('user_id', Auth::user()->user_id)
            ->first();

        return view('live-feedback.play', compact('course', 'module', 'session', 'response'));
    }

    public function partial(Request $request, LiveFeedbackSession $session)
    {
        $this->authorizeParticipant($session);

        $data = $this->validatedRatings($request);
        $this->sessions->savePartial($session, Auth::user()->user_id, $data);

        return response()->json(['ok' => true]);
    }

    public function submit(Request $request, LiveFeedbackSession $session)
    {
        $this->authorizeParticipant($session);

        $data = $this->validatedRatings($request, required: true);
        $this->sessions->submit($session, Auth::user()->user_id, $data);

        return redirect()
            ->route('curriculum.show', $session->course_id)
            ->with('success', __('pages.module_feedback_saved'));
    }

    private function authorizeParticipant(LiveFeedbackSession $session): void
    {
        abort_unless($session->isLive(), 403);
        abort_unless(Auth::user()->isStudent(), 403);
    }

    private function validatedRatings(Request $request, bool $required = false): array
    {
        $rules = [
            'lecture_rating' => ($required ? 'required' : 'nullable').'|integer|min:1|max:5',
            'lecture_comments' => 'nullable|string|max:2000',
            'speaker_rating' => ($required ? 'required' : 'nullable').'|integer|min:1|max:5',
            'speaker_comments' => 'nullable|string|max:2000',
            'workshop_rating' => ($required ? 'required' : 'nullable').'|integer|min:1|max:5',
            'workshop_comments' => 'nullable|string|max:2000',
            'timing_rating' => ($required ? 'required' : 'nullable').'|integer|min:1|max:5',
            'timing_comments' => 'nullable|string|max:2000',
            'content_rating' => ($required ? 'required' : 'nullable').'|integer|min:1|max:5',
            'content_comments' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ];

        return $request->validate($rules);
    }
}
