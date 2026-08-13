<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackIdentityRevealRequest;
use App\Services\FeedbackIdentityRevealService;
use Illuminate\Support\Facades\Auth;

class FeedbackIdentityRevealController extends Controller
{
    public function __construct(
        private FeedbackIdentityRevealService $revealService
    ) {}

    public function index()
    {
        $pending = FeedbackIdentityRevealRequest::query()
            ->with([
                'requester',
                'survey.course',
                'survey.module',
                'submission',
                'answer.question',
            ])
            ->where('status', FeedbackIdentityRevealRequest::STATUS_PENDING)
            ->latest('reveal_request_id')
            ->paginate(30);

        return view('superadmin.feedback-reveal.index', compact('pending'));
    }

    public function approve(FeedbackIdentityRevealRequest $revealRequest)
    {
        $this->revealService->approve($revealRequest, Auth::user());

        return back()->with('success', __('pages.feedback_reveal_approved'));
    }

    public function deny(FeedbackIdentityRevealRequest $revealRequest)
    {
        $this->revealService->deny($revealRequest, Auth::user());

        return back()->with('success', __('pages.feedback_reveal_denied'));
    }
}
