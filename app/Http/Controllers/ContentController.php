<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Content;
use App\Models\ContentFeedback;
use Illuminate\Support\Facades\Auth;

class ContentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $contents = Content::orderBy('session_date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->get();

        return view('contents.index', compact('contents'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('contents.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'session_title' => 'required|string|max:255',
            'session_date' => 'required|date',
            'lecture_name' => 'required|string|max:255',
            'speaker_name' => 'required|string|max:255',
            'audio_link' => 'nullable|url|max:500',
            'slides_link' => 'nullable|url|max:500',
            'description' => 'nullable|string',
        ]);

        Content::create($request->all());

        return redirect()->route('contents.index')
                        ->with('success', 'تم إضافة المحتوى بنجاح');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $content = Content::findOrFail($id);
        $userFeedback = $content->userFeedback(Auth::id());
        
        return view('contents.show', compact('content', 'userFeedback'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $content = Content::findOrFail($id);
        return view('contents.edit', compact('content'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'session_title' => 'required|string|max:255',
            'session_date' => 'required|date',
            'lecture_name' => 'required|string|max:255',
            'speaker_name' => 'required|string|max:255',
            'audio_link' => 'nullable|url|max:500',
            'slides_link' => 'nullable|url|max:500',
            'description' => 'nullable|string',
        ]);

        $content = Content::findOrFail($id);
        $content->update($request->all());

        return redirect()->route('contents.index')
                        ->with('success', 'تم تحديث المحتوى بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $content = Content::findOrFail($id);
        $content->delete();

        return redirect()->route('contents.index')
                        ->with('success', 'تم حذف المحتوى بنجاح');
    }

    /**
     * Store feedback for a content item
     */
    public function storeFeedback(Request $request, $contentId)
    {
        $request->validate([
            'lecture_rating' => 'nullable|integer|min:1|max:5',
            'lecture_comments' => 'nullable|string|max:1000',
            'speaker_rating' => 'nullable|integer|min:1|max:5',
            'speaker_comments' => 'nullable|string|max:1000',
            'general_feedback' => 'nullable|string|max:1000',
        ]);

        $content = Content::findOrFail($contentId);
        
        // Check if user already submitted feedback
        $existingFeedback = $content->userFeedback(Auth::id());
        
        if ($existingFeedback) {
            // Update existing feedback
            $existingFeedback->update($request->all());
            $message = 'تم تحديث التغذية الراجعة بنجاح';
        } else {
            // Create new feedback
            ContentFeedback::create([
                'user_id' => Auth::id(),
                'content_id' => $contentId,
                'lecture_rating' => $request->lecture_rating,
                'lecture_comments' => $request->lecture_comments,
                'speaker_rating' => $request->speaker_rating,
                'speaker_comments' => $request->speaker_comments,
                'general_feedback' => $request->general_feedback,
            ]);
            $message = 'تم إرسال التغذية الراجعة بنجاح';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Show feedback form for a content item
     */
    public function showFeedbackForm($contentId)
    {
        $content = Content::findOrFail($contentId);
        $userFeedback = $content->userFeedback(Auth::id());
        
        return view('contents.feedback', compact('content', 'userFeedback'));
    }
}
