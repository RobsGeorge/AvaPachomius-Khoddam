<?php

namespace App\Services;

use App\Models\User;

/**
 * Resolve which locale to use when sending a templated email to a recipient.
 *
 * Priority: user communication preference → template default → Arabic.
 */
class EmailLocaleResolver
{
    public function __construct(
        private EmailTemplateCatalog $catalog,
    ) {}

    public function forRecipient(
        ?User $user,
        string $family,
        string $templateKey,
        ?int $courseId = null,
    ): string {
        if ($user && EmailTemplateCatalog::userCommunicationLocaleColumnReady()) {
            $pref = $user->communication_locale;
            if (is_string($pref) && in_array($pref, EmailTemplateCatalog::LOCALES, true)) {
                return $pref;
            }
        }

        return $this->catalog->defaultLocale($family, $templateKey, $courseId);
    }
}
