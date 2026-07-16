<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseCertificate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $certificates = CourseCertificate::query()
            ->with(['course', 'graduationStudent'])
            ->where('user_id', $user->user_id)
            ->orderByDesc('issued_at')
            ->orderByDesc('certificate_id')
            ->get();

        return response()->json([
            'data' => $certificates->map(function (CourseCertificate $c) {
                return [
                    'certificate_uuid' => $c->certificate_uuid,
                    'course_id' => $c->course_id,
                    'course_title' => $c->course
                        ? (method_exists($c->course, 'localizedTitle')
                            ? $c->course->localizedTitle()
                            : $c->course->title)
                        : null,
                    'issued_at' => $c->issued_at?->toIso8601String(),
                    'graduated' => (bool) ($c->graduationStudent?->graduated),
                    'download_path' => '/api/v1/certificates/'.$c->certificate_uuid,
                ];
            })->values(),
        ]);
    }

    public function download(Request $request, string $uuid): Response
    {
        /** @var User $user */
        $user = $request->user();

        $certificate = CourseCertificate::query()
            ->where('certificate_uuid', $uuid)
            ->with(['graduationStudent', 'user', 'course'])
            ->firstOrFail();

        abort_unless(
            (int) $certificate->user_id === (int) $user->user_id || ($user->is_superadmin ?? false),
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
