<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Mint printable attendance QR URLs bound to person_id (not user_id).
 * Staff scan still requires an authenticated attendance.staff session.
 */
class AttendanceQrService
{
    public function urlForPerson(Person $person): string
    {
        return URL::signedRoute('attendance.sessions', [
            'person_id' => $person->person_id,
        ]);
    }

    public function urlForUser(User $user): string
    {
        if ($user->person_id) {
            return $this->urlForPerson(
                Person::withoutTenancy()->findOrFail($user->person_id)
            );
        }

        // Legacy fallback while person_id is missing on the account.
        return route('attendance.sessions', ['user_id' => $user->user_id], true);
    }
}
