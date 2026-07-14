<?php

namespace App\Services;

use App\Mail\CourseGraduationMail;
use App\Models\Course;
use App\Models\CourseGraduation;
use App\Models\CourseGraduationEmailTemplate;
use App\Models\CourseGraduationStudent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CourseGraduationMailService
{
    public function __construct(
        private EmailLocaleResolver $localeResolver,
    ) {}

    public function ensureDefaults(?int $courseId = null): void
    {
        foreach (['en', 'ar'] as $locale) {
            foreach (CourseGraduationEmailTemplate::keys() as $key) {
                CourseGraduationEmailTemplate::query()->firstOrCreate(
                    [
                        'course_id' => $courseId,
                        'template_key' => $key,
                        'locale' => $locale,
                    ],
                    [
                        'subject' => __("course_graduation.email_subjects.{$key}", [], $locale),
                        'body_html' => __("course_graduation.email_bodies.{$key}", [], $locale),
                    ]
                );
            }
        }
    }

    public function sendGraduationAnnouncements(Course $course, CourseGraduation $graduation): void
    {
        $graduation->loadMissing(['students.user']);

        foreach ($graduation->students as $row) {
            if ($row->emailed_at !== null) {
                continue;
            }

            $this->sendToStudent(
                $row->user,
                CourseGraduationEmailTemplate::KEY_GRADUATION_ANNOUNCED,
                $course,
                $row
            );

            $row->update(['emailed_at' => now()]);
        }
    }

    public function sendToStudent(
        User $user,
        string $templateKey,
        Course $course,
        CourseGraduationStudent $row,
        ?string $certificateUrl = null,
    ): void {
        if (! filled($user->email)) {
            return;
        }

        $this->ensureDefaults($course->course_id);
        $this->ensureDefaults(null);

        $locale = $this->localeResolver->forRecipient(
            $user,
            EmailTemplateCatalog::FAMILY_COURSE_GRADUATION,
            $templateKey,
            $course->course_id
        );
        $template = CourseGraduationEmailTemplate::query()
            ->where('template_key', $templateKey)
            ->where('locale', $locale)
            ->where(function ($q) use ($course) {
                $q->where('course_id', $course->course_id)
                    ->orWhereNull('course_id');
            })
            ->orderByRaw('course_id IS NULL')
            ->first();

        if (! $template) {
            return;
        }

        $replacements = $this->buildReplacements($user, $course, $row, $certificateUrl);

        try {
            Mail::to($user->email)->send(new CourseGraduationMail(
                $user,
                $template,
                $replacements
            ));
        } catch (\Throwable $e) {
            Log::warning('Course graduation email failed', [
                'user_id' => $user->user_id,
                'course_id' => $course->course_id,
                'template' => $templateKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string, string> */
    public function buildReplacements(
        User $user,
        Course $course,
        CourseGraduationStudent $row,
        ?string $certificateUrl = null,
    ): array {
        return [
            'student_name'    => $user->displayName(),
            'course_title'    => (string) $course->title,
            'course_year'     => (string) $course->year,
            'final_grade'     => number_format($row->final_total_grade, 1),
            'letter_grade'    => $row->letter_grade,
            'grades_url'      => route('courses.final-grades', $course->course_id),
            'certificate_url' => $certificateUrl ?? '',
            'graduation_date' => $row->created_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
        ];
    }
}
