<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Lang;

class StudentOnboardingService
{
    /** @return list<string> */
    public function supportedLocales(): array
    {
        return config('translation.supported_locales', ['ar', 'en']);
    }

    public function localeForWizard(): string
    {
        $locale = session('locale');

        if (is_string($locale) && in_array($locale, $this->supportedLocales(), true)) {
            return $locale;
        }

        return 'en';
    }

    public function shouldShow(User $user): bool
    {
        if (! $user->isStudent() || $user->isInstructorOrAdmin()) {
            return false;
        }

        return ! $user->hasRealDateAttribute('student_onboarding_completed_at');
    }

    /** @return list<array{icon: string, title: string, body: string}> */
    public function steps(?string $locale = null): array
    {
        $locale ??= $this->localeForWizard();

        return [
            [
                'icon' => 'bi-stars',
                'title' => Lang::get('onboarding.steps.welcome.title', [], $locale),
                'body' => Lang::get('onboarding.steps.welcome.body', [], $locale),
            ],
            [
                'icon' => 'bi-list-nested',
                'title' => Lang::get('onboarding.steps.navigation.title', [], $locale),
                'body' => Lang::get('onboarding.steps.navigation.body', [], $locale),
            ],
            [
                'icon' => 'bi-grid-3x3-gap',
                'title' => Lang::get('onboarding.steps.entry_points.title', [], $locale),
                'body' => Lang::get('onboarding.steps.entry_points.body', [], $locale),
            ],
            [
                'icon' => 'bi-bell',
                'title' => Lang::get('onboarding.steps.announcements.title', [], $locale),
                'body' => Lang::get('onboarding.steps.announcements.body', [], $locale),
            ],
            [
                'icon' => 'bi-check-circle',
                'title' => Lang::get('onboarding.steps.finish.title', [], $locale),
                'body' => Lang::get('onboarding.steps.finish.body', [], $locale),
            ],
        ];
    }

    public function complete(User $user): void
    {
        $user->forceFill([
            'student_onboarding_completed_at' => now(),
        ])->save();
    }
}
