<?php

namespace App\Services\People;

use App\Models\ChurchService;
use App\Models\Course;
use App\Support\People\PortalAccountPreference;

class PortalAccountPreferenceResolver
{
    public function forCourse(?Course $course, ?ChurchService $service = null): ?string
    {
        if ($course && filled($course->portal_account_preference)) {
            return $course->portal_account_preference;
        }

        $service ??= $course?->service_id
            ? ChurchService::withoutTenancy()->find($course->service_id)
            : null;

        return $this->forService($service);
    }

    public function forService(?ChurchService $service): ?string
    {
        if (! $service) {
            return null;
        }

        $pref = $service->portal_account_preference;

        return PortalAccountPreference::isValid($pref) ? $pref : null;
    }

    public function defaultsInviteToPortal(?Course $course = null, ?ChurchService $service = null): bool
    {
        return PortalAccountPreference::defaultsInvite(
            $course ? $this->forCourse($course, $service) : $this->forService($service)
        );
    }
}
