<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseCertificate;
use App\Models\CourseCertificateTemplate;
use App\Models\CourseGraduation;
use App\Models\CourseGraduationStudent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    public function __construct(
        private CourseGraduationMailService $mail,
    ) {}

    public function ensureDefaultTemplates(Course $course): void
    {
        foreach (['en', 'ar'] as $locale) {
            CourseCertificateTemplate::query()->firstOrCreate(
                [
                    'course_id' => $course->course_id,
                    'locale' => $locale,
                ],
                [
                    'name' => __('course_graduation.certificate_default_name', [], $locale),
                    'body_html' => __("course_graduation.certificate_default_body", [], $locale),
                    'is_default' => true,
                ]
            );
        }
    }

    public function issueForGraduation(Course $course, CourseGraduation $graduation): void
    {
        $this->ensureDefaultTemplates($course);
        $graduation->loadMissing(['students.user', 'students.certificate']);

        foreach ($graduation->students as $row) {
            if (! $row->graduated || $row->certificate) {
                continue;
            }

            $this->issueForStudent($course, $row);
        }
    }

    public function issueForStudent(Course $course, CourseGraduationStudent $row): CourseCertificate
    {
        $certificate = CourseCertificate::create([
            'course_graduation_student_id' => $row->id,
            'user_id'                      => $row->user_id,
            'course_id'                    => $course->course_id,
            'certificate_uuid'             => (string) Str::uuid(),
            'issued_at'                    => now(),
        ]);

        $pdfPath = $this->generatePdf($course, $row, $certificate);
        $certificate->update(['pdf_path' => $pdfPath]);

        $this->mail->sendToStudent(
            $row->user,
            \App\Models\CourseGraduationEmailTemplate::KEY_CERTIFICATE_ISSUED,
            $course,
            $row,
            route('certificates.download', $certificate->certificate_uuid)
        );

        return $certificate->fresh();
    }

    public function generatePdf(Course $course, CourseGraduationStudent $row, CourseCertificate $certificate): string
    {
        $row->loadMissing('user');
        $locale = app()->getLocale();
        $template = CourseCertificateTemplate::query()
            ->where('course_id', $course->course_id)
            ->where('locale', $locale)
            ->where('is_default', true)
            ->first()
            ?? CourseCertificateTemplate::query()
                ->where('course_id', $course->course_id)
                ->where('locale', 'en')
                ->first();

        $bodyHtml = $template?->body_html ?? __('course_graduation.certificate_default_body');
        $html = $this->renderTemplate($bodyHtml, [
            'student_name'    => $row->user->displayName(),
            'course_title'    => (string) $course->title,
            'course_year'     => (string) $course->year,
            'final_grade'     => number_format($row->final_total_grade, 1),
            'letter_grade'    => $row->letter_grade,
            'graduation_date' => $certificate->issued_at->format('Y-m-d'),
            'certificate_id'  => $certificate->certificate_uuid,
        ]);

        $pdf = Pdf::loadHTML($this->wrapCertificateHtml($html, $course->title));

        $relativePath = 'certificates/'.$certificate->certificate_uuid.'.pdf';
        Storage::disk('local')->put($relativePath, $pdf->output());

        return $relativePath;
    }

    /** @param array<string, string> $replacements */
    public function renderTemplate(string $content, array $replacements): string
    {
        $rendered = $content;

        foreach ($replacements as $key => $value) {
            $rendered = str_replace('{{'.$key.'}}', $value, $rendered);
        }

        return $rendered;
    }

    private function wrapCertificateHtml(string $bodyHtml, string $title): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
            .e($title)
            .'</title><style>body{font-family:DejaVu Sans,sans-serif;text-align:center;padding:40px;} .cert{border:4px double #333;padding:40px;}</style></head><body><div class="cert">'
            .$bodyHtml
            .'</div></body></html>';
    }
}
