<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\ContentFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Legacy content feedback only — the old `content` table is superseded by lectures.
 */
class ContentController extends Controller
{
    public function showFeedbackForm(string $id)
    {
        $content = Content::findOrFail($id);
        $userFeedback = $content->userFeedback(Auth::id());

        return view('contents.feedback', compact('content', 'userFeedback'));
    }

    public function storeFeedback(Request $request, string $id)
    {
        $content = Content::findOrFail($id);

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comments' => 'nullable|string|max:1000',
        ]);

        ContentFeedback::updateOrCreate(
            [
                'content_id' => $content->content_id,
                'user_id' => Auth::id(),
            ],
            [
                'rating' => $request->rating,
                'comments' => $request->comments,
            ]
        );

        return redirect()->route('curriculum.index')
                        ->with('success', __('pages.legacy_feedback_saved'));
    }
}
