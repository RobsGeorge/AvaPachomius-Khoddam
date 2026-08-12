<?php

namespace App\Services;

use App\Models\CourseApplicationForm;
use App\Models\RegistrationApplication;
use App\Models\User;

class CourseApplicationSnapshotPrefillService
{
    /** @return array<string, mixed> */
    public function buildFromUser(User $user, CourseApplicationForm $form): array
    {
        $profile = $this->profileValues($user);
        $snapshot = [];
        $form->loadMissing('steps.fields');

        foreach ($form->steps as $step) {
            foreach ($step->fields as $field) {
                if (! $field->isInput()) {
                    continue;
                }

                $key = $field->field_key;
                $snapshot[$key] = $profile[$key] ?? '';
            }
        }

        return $snapshot;
    }

    /** @return array<string, string> */
    private function profileValues(User $user): array
    {
        $values = [];

        foreach (RegistrationApplication::REVIEWABLE_FIELDS as $field) {
            if ($field === 'date_of_birth') {
                $values[$field] = $user->date_of_birth?->format('Y-m-d') ?? '';

                continue;
            }

            $values[$field] = (string) ($user->{$field} ?? '');
        }

        $values['phone'] = $values['mobile_number'] ?? '';
        $values['mobile'] = $values['mobile_number'] ?? '';
        $values['birth_date'] = $values['date_of_birth'] ?? '';

        return $values;
    }
}
