<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCertificateTemplate;
use App\Services\CertificateService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;

class CourseCertificateTemplateController extends Controller
{
    public function __construct(
        private CertificateService $certificates,
        private StudentRosterService $roster,
    ) {}

    public function edit(string $courseId)
    {
        $this->roster->authorizeCourse(auth()->user(), $courseId);
        $course = Course::findOrFail($courseId);
        $this->certificates->ensureDefaultTemplates($course);

        $locale = app()->getLocale();
        $template = CourseCertificateTemplate::query()
            ->where('course_id', $course->course_id)
            ->where('locale', $locale)
            ->first();

        return view('admin.course-closing.certificate-template', compact('course', 'template'));
    }

    public function update(Request $request, string $courseId)
    {
        $this->roster->authorizeCourse(auth()->user(), $courseId);
        $course = Course::findOrFail($courseId);

        $data = $request->validate([
            'name'      => 'required|string|max:150',
            'body_html' => 'required|string|max:50000',
        ]);

        $locale = app()->getLocale();

        CourseCertificateTemplate::query()->updateOrCreate(
            [
                'course_id' => $course->course_id,
                'locale' => $locale,
            ],
            [
                'name' => $data['name'],
                'body_html' => $data['body_html'],
                'is_default' => true,
            ]
        );

        return redirect()
            ->route('courses.certificate-template.edit', $course->course_id)
            ->with('success', __('course_graduation.certificate_saved'));
    }
}
