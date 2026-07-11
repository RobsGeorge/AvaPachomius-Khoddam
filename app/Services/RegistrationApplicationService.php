<?php

namespace App\Services;

use App\Models\RegistrationApplication;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class RegistrationApplicationService
{
    public function snapshotFromUser(User $user): array
    {
        $snapshot = [];

        foreach (RegistrationApplication::REVIEWABLE_FIELDS as $field) {
            if ($field === 'date_of_birth') {
                $snapshot[$field] = $user->date_of_birth?->format('Y-m-d');
                continue;
            }

            $snapshot[$field] = $user->{$field} ?? '';
        }

        return $snapshot;
    }

    public function createFromUser(User $user): RegistrationApplication
    {
        $latestVersion = RegistrationApplication::query()
            ->where('user_id', $user->user_id)
            ->max('version');

        return RegistrationApplication::create([
            'user_id' => $user->user_id,
            'status' => RegistrationApplication::STATUS_PENDING_REVIEW,
            'snapshot' => $this->snapshotFromUser($user),
            'version' => ($latestVersion ?? 0) + 1,
            'submitted_at' => now(),
        ]);
    }

    public function latestForUser(User $user): ?RegistrationApplication
    {
        return RegistrationApplication::query()
            ->where('user_id', $user->user_id)
            ->latest('id')
            ->first();
    }

    public function syncUserApplicationStatus(User $user, string $status): void
    {
        if (! Schema::hasColumn('user', 'application_status')) {
            return;
        }

        $user->forceFill(['application_status' => $status])->save();
    }

    public function isApproved(User $user): bool
    {
        if (! Schema::hasColumn('user', 'application_status')) {
            return (bool) $user->is_verified;
        }

        return $user->application_status === RegistrationApplication::STATUS_APPROVED
            && (bool) $user->is_verified;
    }

    public function redirectRouteFor(User $user): string
    {
        $status = $user->application_status ?? RegistrationApplication::STATUS_PENDING_REVIEW;

        return match ($status) {
            RegistrationApplication::STATUS_NEEDS_CORRECTION => 'application.edit',
            RegistrationApplication::STATUS_REJECTED => 'application.status',
            default => 'application.status',
        };
    }
}
