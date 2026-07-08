<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LiveFeedbackSession;
use App\Models\Module;
use App\Services\LiveFeedbackSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiveFeedbackHostController extends Controller
{
    public function __construct(
        private LiveFeedbackSessionService $sessions
    ) {}

    public function start(Request $request, string $courseId, string $moduleId)
    {
        $course = Course::findOrFail($courseId);
        $module = $course->modules()->where('modules.module_id', $moduleId)->firstOrFail();

        abort_unless(Auth::user()->isInstructorOrAdmin(), 403);

        $session = $this->sessions->startForModule($course, $module, Auth::user()->user_id, true);

        return redirect()->route('live-feedback.present', $session);
    }

    public function present(LiveFeedbackSession $session)
    {
        abort_unless(Auth::user()->isInstructorOrAdmin(), 403);

        $session->load(['course', 'module']);
        $aggregates = $this->sessions->aggregates($session);

        return view('live-feedback.present', compact('session', 'aggregates'));
    }

    public function close(LiveFeedbackSession $session)
    {
        abort_unless(Auth::user()->isInstructorOrAdmin(), 403);

        $this->sessions->closeSession($session);

        return redirect()
            ->route('live-feedback.present', $session)
            ->with('success', __('pages.live_feedback_closed'));
    }

    public function step(Request $request, LiveFeedbackSession $session)
    {
        abort_unless(Auth::user()->isInstructorOrAdmin(), 403);

        $data = $request->validate(['current_step' => 'required|integer|min:0|max:10']);
        $session->update(['current_step' => $data['current_step']]);
        $this->sessions->broadcast($session->fresh('responses'));

        return back();
    }
}
