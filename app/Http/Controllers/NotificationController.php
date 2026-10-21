<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\FeedbackSurvey;
use App\Models\UserNotification;
use App\Services\NotificationFeedService;
use App\Support\InternalRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationFeedService $feed
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $filter = $request->query('filter', 'all');

        return view('notifications.index', [
            'notifications' => $this->feed->inbox($user, $filter === 'all' ? null : $filter),
            'filter' => $filter,
            'filters' => $this->feed->availableFilters(),
            'unreadCount' => $this->feed->unreadCount($user),
        ]);
    }

    public function show(UserNotification $notification)
    {
        $user = Auth::user();
        abort_unless($notification->user_id === $user->user_id, 403);

        $this->feed->markRead($notification);

        $destination = $this->destinationPath($notification);
        if ($destination) {
            return redirect($destination);
        }

        return redirect()->route('notifications.index');
    }

    /**
     * Follow an action_url only when it targets this application (F9 — prevent open
     * redirects). Accepts app-relative paths (but not protocol-relative "//host") and
     * absolute URLs whose host matches the app host; rejects any cross-host target.
     * Always returns a path so APP_URL host/port mismatches cannot hang the browser.
     */
    private function destinationPath(UserNotification $notification): ?string
    {
        $surveyPath = $this->linkedSurveyPath($notification);
        if ($surveyPath) {
            return $surveyPath;
        }

        return InternalRedirect::path($notification->action_url);
    }

    private function linkedSurveyPath(UserNotification $notification): ?string
    {
        $surveyId = $notification->source_type === 'feedback_survey'
            ? (int) $notification->source_id
            : (int) ($notification->metadata['survey_id'] ?? 0);

        if ($surveyId < 1 && $notification->source_type === 'announcement' && $notification->source_id) {
            $announcement = Announcement::query()->find($notification->source_id);
            $surveyId = (int) ($announcement?->linkedFeedbackSurvey()?->survey_id ?? 0);
        }

        if ($surveyId < 1) {
            $fromAction = InternalRedirect::path($notification->action_url);
            if ($fromAction && preg_match('#^/announcements/(\d+)#', $fromAction, $matches)) {
                $announcement = Announcement::query()->find((int) $matches[1]);
                $surveyId = (int) ($announcement?->linkedFeedbackSurvey()?->survey_id ?? 0);
            }
        }

        if ($surveyId < 1) {
            return null;
        }

        $survey = FeedbackSurvey::query()->find($surveyId);
        if (! $survey || $survey->status === FeedbackSurvey::STATUS_DRAFT) {
            return null;
        }

        return route('feedback.surveys.show', $survey, false);
    }

    public function markAllRead()
    {
        $this->feed->markAllRead(Auth::user());

        return back()->with('success', __('notifications.all_marked_read'));
    }

    public function toggleRead(UserNotification $notification)
    {
        $user = Auth::user();
        abort_unless($notification->user_id === $user->user_id, 403);

        if ($notification->isUnread()) {
            $this->feed->markRead($notification);

            return back()->with('success', __('notifications.marked_read'));
        }

        $this->feed->markUnread($notification);

        return back()->with('success', __('notifications.marked_unread'));
    }
}
