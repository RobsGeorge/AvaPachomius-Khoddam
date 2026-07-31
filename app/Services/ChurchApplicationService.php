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
            'contact_email' => $application->contact_email,
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

        AuditLogService::recordEvent('church_application.approved', $this->auditPayload($application, $note));
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

        AuditLogService::recordEvent('church_application.rejected', $this->auditPayload($application, $note));
    }

    /** @return array<string, mixed> */
    private function auditPayload(ChurchApplication $application, ?string $note = null): array
    {
        return [
            'church_application_id' => $application->church_application_id,
            'requested_name' => $application->requested_name,
            'requested_short_name' => $application->requested_short_name,
            'contact_name' => $application->contact_name,
            'contact_email' => $application->contact_email,
            'contact_mobile' => $application->contact_mobile,
            'place_district' => $application->place_district,
            'place_governorate' => $application->place_governorate,
            'place_country_code' => $application->place_country_code,
            'admin_note' => $note,
            'status' => $application->status,
        ];
    }
}
