<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\CoursePermissionResolver;
use App\Services\EmailTemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseEmailTemplateController extends Controller
{
    public function __construct(
        private EmailTemplateCatalog $catalog,
        private CoursePermissionResolver $resolver,
    ) {}

    public function index(Request $request, Course $course)
    {
        $user = $request->user();
        abort_unless($user && $this->canManage($user, $course), 403);

        $this->catalog->ensureCourseDefaults($course);

        $families = [];
        foreach ($this->catalog->courseFamilies() as $familyDef) {
            $templates = $this->catalog->templatesForFamily($familyDef['family'], $course->course_id);
            $defaults = [];
            foreach ($familyDef['keys'] as $key) {
                $defaults[$key] = $this->catalog->defaultLocale($familyDef['family'], $key, $course->course_id);
            }
            $families[] = array_merge($familyDef, [
                'templates' => $templates,
                'defaults' => $defaults,
            ]);
        }

        $editLocale = $request->query('locale', app()->getLocale());
        if (! in_array($editLocale, EmailTemplateCatalog::LOCALES, true)) {
            $editLocale = 'ar';
        }

        return view('email-templates.course-index', [
            'course' => $course,
            'families' => $families,
            'locales' => $this->catalog->locales(),
            'editLocale' => $editLocale,
            'catalog' => $this->catalog,
        ]);
    }

    public function update(Request $request, Course $course)
    {
        $user = $request->user();
        abort_unless($user && $this->canManage($user, $course), 403);

        $validated = $request->validate([
            'family' => ['required', 'string', Rule::in([
                EmailTemplateCatalog::FAMILY_COURSE_APPLICATION,
                EmailTemplateCatalog::FAMILY_COURSE_GRADUATION,
            ])],
            'templates' => ['required', 'array'],
            'templates.*.subject' => ['required', 'string', 'max:255'],
            'templates.*.body_html' => ['required', 'string'],
            'defaults' => ['nullable', 'array'],
            'defaults.*' => ['nullable', Rule::in(EmailTemplateCatalog::LOCALES)],
        ]);

        foreach ($validated['templates'] as $id => $row) {
            $this->catalog->updateTemplateRow(
                $validated['family'],
                (int) $id,
                $row['subject'],
                $row['body_html'],
                $course->course_id
            );
        }

        foreach ($validated['defaults'] ?? [] as $templateKey => $locale) {
            if (is_string($locale) && $locale !== '') {
                $this->catalog->setDefaultLocale(
                    $validated['family'],
                    (string) $templateKey,
                    $locale,
                    $course->course_id
                );
            }
        }

        return redirect()
            ->route('courses.email-templates.index', [
                'course' => $course,
                'locale' => $request->input('edit_locale', app()->getLocale()),
            ])
            ->with('success', __('email_templates.saved'));
    }

    public function preview(Request $request, Course $course)
    {
        $user = $request->user();
        abort_unless($user && $this->canManage($user, $course), 403);

        $validated = $request->validate([
            'family' => ['required', 'string'],
            'subject' => ['required', 'string'],
            'body_html' => ['required', 'string'],
        ]);

        $rendered = $this->catalog->renderPreview(
            $validated['subject'],
            $validated['body_html'],
            $this->catalog->sampleReplacements($validated['family'])
        );

        return response()->json($rendered);
    }

    private function canManage($user, Course $course): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        return $this->resolver->canInCourse($user, 'email_templates.manage', $course)
            || $this->resolver->canInCourse($user, 'certificate.manage', $course)
            || $this->resolver->canInCourse($user, 'graduation.configure', $course)
            || $this->resolver->canInSystem($user, 'course_application.form_builder');
    }
}
