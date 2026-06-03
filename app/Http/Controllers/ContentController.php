<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\ContentFeedback;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentController extends Controller
{
    /**
     * Curriculum entry: courses with modules & lectures (pillar headers).
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['admin', 'instructor'])) {
            $courses = Course::orderBy('title')->get();
        } else {
            $courses = $user->courses()->distinct()->orderBy('title')->get();
        }

        if ($courses->count() === 1) {
            return redirect()->route('course-content.show', $courses->first()->course_id);
        }

        return view('contents.index', compact('courses'));
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
        Content::findOrFail($id)->delete();

        return redirect()->route('contents.index')
                        ->with('success', 'تم حذف المحتوى بنجاح');
    }

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

        return redirect()->route('contents.index')
                        ->with('success', 'تم حفظ ملاحظاتك بنجاح');
    }
}
