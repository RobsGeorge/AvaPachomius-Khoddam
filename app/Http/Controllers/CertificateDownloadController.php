<?php

namespace App\Http\Controllers;

use App\Models\CourseCertificate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CertificateDownloadController extends Controller
{
    public function download(string $uuid)
    {
        $certificate = CourseCertificate::where('certificate_uuid', $uuid)
            ->with(['graduationStudent', 'user', 'course'])
            ->firstOrFail();

        abort_unless(
            Auth::id() === $certificate->user_id
            || auth()->user()?->roles?->contains(fn ($r) => in_array(strtolower($r->role_name), ['admin', 'instructor'], true)),
            403
        );

        abort_unless($certificate->graduationStudent?->graduated, 403);

        if ($certificate->pdf_path && Storage::disk('local')->exists($certificate->pdf_path)) {
            return Storage::disk('local')->download(
                $certificate->pdf_path,
                'certificate-'.$certificate->certificate_uuid.'.pdf'
            );
        }

        $pdfPath = app(\App\Services\CertificateService::class)
            ->generatePdf($certificate->course, $certificate->graduationStudent, $certificate);

        $certificate->update(['pdf_path' => $pdfPath]);

        return Storage::disk('local')->download(
            $pdfPath,
            'certificate-'.$certificate->certificate_uuid.'.pdf'
        );
    }
}
