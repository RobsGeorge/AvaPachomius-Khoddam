<?php

namespace App\Http\View\Composers;

use App\Services\AnnouncementService;
use App\Services\ProfilePhotoGateService;
use App\Services\StudentOnboardingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AppLayoutComposer
{
    public function __construct(
        private ProfilePhotoGateService $photoGate,
        private AnnouncementService $announcements,
        private StudentOnboardingService $onboarding
    ) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->photoGate->ensureGraceStarted($user);

        $view->with('profilePhotoWarning', $this->photoGate->shouldShowWarningBanner($user));
        $view->with('profilePhotoPending', $this->photoGate->shouldShowPendingBanner($user));
        $view->with('profilePhotoRejected', $this->photoGate->shouldShowRejectedBanner($user));
        $view->with('profilePhotoRejectionNote', $user->profile_photo_rejection_note);
        $view->with('profilePhotoDeadline', $this->photoGate->deadlineFor($user));
        $view->with('profilePhotoDaysRemaining', $this->photoGate->daysRemaining($user));
        $view->with('profilePhotoHardBlocked', $this->photoGate->isHardBlocked($user));

        if ($user->isStudent()) {
            $view->with('unreadAnnouncementCount', $this->announcements->unreadCount($user));
            $view->with('activeAnnouncementBanners', $this->announcements->activeBanners($user));
        } else {
            $view->with('unreadAnnouncementCount', 0);
            $view->with('activeAnnouncementBanners', collect());
        }

        if ($this->onboarding->shouldShow($user)) {
            $locale = $this->onboarding->localeForWizard();
            $view->with('showStudentOnboarding', true);
            $view->with('studentOnboardingSteps', $this->onboarding->steps($locale));
            $view->with('studentOnboardingLocale', $locale);
        } else {
            $view->with('showStudentOnboarding', false);
        }
    }
}
