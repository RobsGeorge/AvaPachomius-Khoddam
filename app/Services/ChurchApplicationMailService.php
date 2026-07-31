<?php

namespace App\Services;

use App\Mail\ChurchApplicationSubmittedMail;
use App\Models\ChurchApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ChurchApplicationMailService
{
    public function sendVerification(ChurchApplication $application): void
    {
        if (! filled($application->contact_email) || ! filled($application->public_token)) {
            return;
        }

        try {
            Mail::to($application->contact_email)->send(new ChurchApplicationSubmittedMail($application));
        } catch (\Throwable $e) {
            Log::warning('Church application verification email failed', [
                'church_application_id' => $application->church_application_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
