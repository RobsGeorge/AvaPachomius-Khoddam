<?php

namespace App\Services;

use App\Mail\ChurchApplicationProvisionedMail;
use App\Mail\ChurchApplicationSubmittedMail;
use App\Models\Church;
use App\Models\ChurchApplication;
use App\Models\User;
use App\Support\ChurchHost;
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

    public function notifySuperadminsOfProvision(ChurchApplication $application, Church $church): void
    {
        $recipients = User::query()
            ->where('is_superadmin', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $adminUrl = ChurchHost::consoleUrl('/superadmin/church-applications/'.$application->church_application_id);

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new ChurchApplicationProvisionedMail($application, $church, $adminUrl));
            } catch (\Throwable $e) {
                Log::warning('Church application provisioned admin email failed', [
                    'church_application_id' => $application->church_application_id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
