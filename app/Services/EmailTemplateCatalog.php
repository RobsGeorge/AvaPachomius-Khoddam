<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseApplicationReviewTemplate;
use App\Models\CourseGraduationEmailTemplate;
use App\Models\EmailTemplateMeta;
use App\Models\RegistrationReviewTemplate;
use App\Models\RoleAssignmentEmailTemplate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog of editable outbound email templates (role, registration, course application, graduation).
 */
class EmailTemplateCatalog
{
    public const FAMILY_ROLE_ASSIGNMENT = 'role_assignment';

    public const FAMILY_REGISTRATION_REVIEW = 'registration_review';

    public const FAMILY_COURSE_APPLICATION = 'course_application';

    public const FAMILY_COURSE_GRADUATION = 'course_graduation';

    public const LOCALES = ['ar', 'en'];

    /** @return list<string> */
    public function locales(): array
    {
        return self::LOCALES;
    }

    /** @return list<array{family: string, label: string, keys: list<string>, placeholders: list<string>, course_scoped: bool}> */
    public function courseFamilies(): array
    {
        return [
            [
                'family' => self::FAMILY_COURSE_APPLICATION,
                'label' => __('email_templates.family_course_application'),
                'keys' => CourseApplicationReviewTemplate::keys(),
                'placeholders' => ['name', 'course', 'note', 'fields_table', 'portal_url', 'correction_url'],
                'course_scoped' => true,
            ],
            [
                'family' => self::FAMILY_COURSE_GRADUATION,
                'label' => __('email_templates.family_course_graduation'),
                'keys' => CourseGraduationEmailTemplate::keys(),
                'placeholders' => ['student_name', 'course_title', 'course_year', 'final_grade', 'letter_grade', 'grades_url', 'certificate_url', 'graduation_date'],
                'course_scoped' => true,
            ],
        ];
    }

    /** @return list<array{family: string, label: string, keys: list<string>, placeholders: list<string>, course_scoped: bool}> */
    public function systemFamilies(): array
    {
        return [
            [
                'family' => self::FAMILY_ROLE_ASSIGNMENT,
                'label' => __('email_templates.family_role_assignment'),
                'keys' => RoleAssignmentEmailTemplate::keys(),
                'placeholders' => RoleAssignmentEmailTemplate::placeholders(),
                'course_scoped' => false,
            ],
            [
                'family' => self::FAMILY_REGISTRATION_REVIEW,
                'label' => __('email_templates.family_registration_review'),
                'keys' => RegistrationReviewTemplate::keys(),
                'placeholders' => RegistrationReviewTemplate::placeholders(),
                'course_scoped' => false,
            ],
        ];
    }

    public function ensureCourseDefaults(Course $course): void
    {
        app(CourseApplicationMailService::class)->ensureDefaults($course->course_id);
        app(CourseApplicationMailService::class)->ensureDefaults(null);
        app(CourseGraduationMailService::class)->ensureDefaults($course->course_id);
        app(CourseGraduationMailService::class)->ensureDefaults(null);

        foreach ($this->courseFamilies() as $family) {
            foreach ($family['keys'] as $key) {
                $this->ensureMeta($family['family'], $key, $course->course_id);
            }
        }
    }

    public function ensureSystemDefaults(): void
    {
        app(RoleAssignmentMailService::class)->ensureDefaults();
        app(RegistrationReviewMailService::class)->ensureDefaults();

        foreach ($this->systemFamilies() as $family) {
            foreach ($family['keys'] as $key) {
                $this->ensureMeta($family['family'], $key, null);
            }
        }
    }

    public function ensureMeta(string $family, string $templateKey, ?int $courseId): void
    {
        if (! EmailTemplateMeta::tableReady()) {
            return;
        }

        EmailTemplateMeta::query()->firstOrCreate(
            [
                'family' => $family,
                'course_id' => $courseId,
                'template_key' => $templateKey,
            ],
            ['default_locale' => 'ar']
        );
    }

    public function defaultLocale(string $family, string $templateKey, ?int $courseId = null): string
    {
        if (! EmailTemplateMeta::tableReady()) {
            return 'ar';
        }

        $query = EmailTemplateMeta::query()
            ->where('family', $family)
            ->where('template_key', $templateKey);

        if ($courseId) {
            $meta = (clone $query)->where('course_id', $courseId)->first()
                ?? (clone $query)->whereNull('course_id')->first();
        } else {
            $meta = $query->whereNull('course_id')->first();
        }

        $locale = $meta?->default_locale;

        return in_array($locale, self::LOCALES, true) ? $locale : 'ar';
    }

    public function setDefaultLocale(string $family, string $templateKey, string $locale, ?int $courseId = null): void
    {
        if (! EmailTemplateMeta::tableReady() || ! in_array($locale, self::LOCALES, true)) {
            return;
        }

        EmailTemplateMeta::query()->updateOrCreate(
            [
                'family' => $family,
                'course_id' => $courseId,
                'template_key' => $templateKey,
            ],
            ['default_locale' => $locale]
        );
    }

    /**
     * @return Collection<string, Collection<string, object>>
     *   keyed by template_key then locale
     */
    public function templatesForFamily(string $family, ?int $courseId = null): Collection
    {
        return match ($family) {
            self::FAMILY_ROLE_ASSIGNMENT => RoleAssignmentEmailTemplate::query()
                ->orderBy('template_key')->orderBy('locale')->get()
                ->groupBy('template_key')
                ->map(fn (Collection $rows) => $rows->keyBy('locale')),
            self::FAMILY_REGISTRATION_REVIEW => RegistrationReviewTemplate::query()
                ->orderBy('template_key')->orderBy('locale')->get()
                ->groupBy('template_key')
                ->map(fn (Collection $rows) => $rows->keyBy('locale')),
            self::FAMILY_COURSE_APPLICATION => CourseApplicationReviewTemplate::query()
                ->where(function ($q) use ($courseId) {
                    $q->where('course_id', $courseId)->orWhereNull('course_id');
                })
                ->orderByRaw('course_id IS NULL')
                ->orderBy('template_key')->orderBy('locale')->get()
                ->groupBy('template_key')
                ->map(function (Collection $rows) use ($courseId) {
                    return $rows
                        ->groupBy('locale')
                        ->map(fn (Collection $localeRows) => $localeRows->firstWhere('course_id', $courseId) ?? $localeRows->first());
                }),
            self::FAMILY_COURSE_GRADUATION => CourseGraduationEmailTemplate::query()
                ->where(function ($q) use ($courseId) {
                    $q->where('course_id', $courseId)->orWhereNull('course_id');
                })
                ->orderByRaw('course_id IS NULL')
                ->orderBy('template_key')->orderBy('locale')->get()
                ->groupBy('template_key')
                ->map(function (Collection $rows) use ($courseId) {
                    return $rows
                        ->groupBy('locale')
                        ->map(fn (Collection $localeRows) => $localeRows->firstWhere('course_id', $courseId) ?? $localeRows->first());
                }),
            default => collect(),
        };
    }

    public function updateTemplateRow(string $family, int $id, string $subject, string $bodyHtml, ?int $courseId = null): void
    {
        match ($family) {
            self::FAMILY_ROLE_ASSIGNMENT => RoleAssignmentEmailTemplate::query()->whereKey($id)->update([
                'subject' => $subject,
                'body_html' => $bodyHtml,
            ]),
            self::FAMILY_REGISTRATION_REVIEW => RegistrationReviewTemplate::query()->whereKey($id)->update([
                'subject' => $subject,
                'body_html' => $bodyHtml,
            ]),
            self::FAMILY_COURSE_APPLICATION => $this->upsertCourseScoped(
                CourseApplicationReviewTemplate::class,
                $id,
                $subject,
                $bodyHtml,
                $courseId
            ),
            self::FAMILY_COURSE_GRADUATION => $this->upsertCourseScoped(
                CourseGraduationEmailTemplate::class,
                $id,
                $subject,
                $bodyHtml,
                $courseId
            ),
            default => null,
        };
    }

    /**
     * When editing a global template while viewing a course, create a course-specific override instead of mutating global.
     */
    private function upsertCourseScoped(string $modelClass, int $id, string $subject, string $bodyHtml, ?int $courseId): void
    {
        /** @var \Illuminate\Database\Eloquent\Model|null $row */
        $row = $modelClass::query()->find($id);
        if (! $row) {
            return;
        }

        if ($courseId && (int) ($row->course_id ?? 0) !== (int) $courseId) {
            $modelClass::query()->updateOrCreate(
                [
                    'course_id' => $courseId,
                    'template_key' => $row->template_key,
                    'locale' => $row->locale,
                ],
                [
                    'subject' => $subject,
                    'body_html' => $bodyHtml,
                ]
            );

            return;
        }

        $row->update([
            'subject' => $subject,
            'body_html' => $bodyHtml,
        ]);
    }

    /** @return array<string, string> */
    public function sampleReplacements(string $family): array
    {
        return match ($family) {
            self::FAMILY_ROLE_ASSIGNMENT => [
                'name' => 'Example Student',
                'role_name' => 'Instructor',
                'course_title' => 'Sample Course 2026',
                'portal_url' => url('/'),
            ],
            self::FAMILY_REGISTRATION_REVIEW, self::FAMILY_COURSE_APPLICATION => [
                'name' => 'Example Applicant',
                'course' => 'Sample Course 2026',
                'note' => 'Example admin note',
                'fields_table' => '<table><tr><td>Field</td><td>Value</td></tr></table>',
                'portal_url' => url('/'),
                'correction_url' => url('/'),
            ],
            self::FAMILY_COURSE_GRADUATION => [
                'student_name' => 'Example Student',
                'course_title' => 'Sample Course',
                'course_year' => '2026',
                'final_grade' => '92.5',
                'letter_grade' => 'ممتاز',
                'grades_url' => url('/'),
                'certificate_url' => url('/'),
                'graduation_date' => now()->format('Y-m-d'),
            ],
            default => [],
        };
    }

    public function renderPreview(string $subject, string $bodyHtml, array $replacements): array
    {
        foreach ($replacements as $key => $value) {
            $subject = str_replace('{{'.$key.'}}', (string) $value, $subject);
            $bodyHtml = str_replace('{{'.$key.'}}', (string) $value, $bodyHtml);
        }

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
        ];
    }

    public static function userCommunicationLocaleColumnReady(): bool
    {
        return Schema::hasColumn('user', 'communication_locale');
    }
}
