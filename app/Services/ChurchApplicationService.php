<?php

namespace App\Services;

use App\Models\ChurchApplication;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ChurchApplicationService
{
    public function verifyEmail(ChurchApplication $application): void
    {
        if ($application->isEmailVerified() && $application->isPending()) {
            return;
        }

        if (! $application->isUnverified() && ! $application->isPending()) {
            throw ValidationException::withMessages([
                'application' => __('church_applications.not_verifiable'),
            ]);
        }

        $application->update([
            'status' => ChurchApplication::STATUS_PENDING,
            'email_verified_at' => $application->email_verified_at ?? now(),
        ]);

        AuditLogService::recordEvent('church_application.email_verified', [
            'church_application_id' => $application->church_application_id,
            'requested_name' => $application->requested_name,
        ]);
    }

    public function approve(ChurchApplication $application, User $reviewer, ?string $note = null): void
    {
        if ($application->isUnverified()) {
            throw ValidationException::withMessages([
                'application' => __('church_applications.email_not_verified'),
            ]);
        }

        if (! $application->isPending()) {
            throw ValidationException::withMessages([
                'application' => __('church_applications.not_pending'),
            ]);
        }

        $application->update([
            'status' => ChurchApplication::STATUS_APPROVED,
            'admin_note' => $note,
            'reviewed_by_user_id' => $reviewer->user_id,
            'reviewed_at' => now(),
        ]);

        AuditLogService::recordEvent('church_application.approved', [
            'church_application_id' => $application->church_application_id,
            'requested_name' => $application->requested_name,
        ]);
    }

    public function reject(ChurchApplication $application, User $reviewer, string $note): void
    {
        if ($application->isUnverified()) {
            throw ValidationException::withMessages([
                'application' => __('church_applications.email_not_verified'),
            ]);
        }

        if (! $application->isPending()) {
            throw ValidationException::withMessages([
                'application' => __('church_applications.not_pending'),
            ]);
        }

        $application->update([
            'status' => ChurchApplication::STATUS_REJECTED,
            'admin_note' => $note,
            'reviewed_by_user_id' => $reviewer->user_id,
            'reviewed_at' => now(),
        ]);

        AuditLogService::recordEvent('church_application.rejected', [
            'church_application_id' => $application->church_application_id,
            'requested_name' => $application->requested_name,
        ]);
    }
}
