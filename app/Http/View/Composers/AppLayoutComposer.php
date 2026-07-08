<?php

namespace App\Http\View\Composers;

use App\Services\AnnouncementService;
use App\Services\ProfilePhotoGateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AppLayoutComposer
{
    public function __construct(
        private ProfilePhotoGateService $photoGate,
        private AnnouncementService $announcements
    ) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->photoGate->ensureGraceStarted($user);

        $view->with('profilePhotoWarning', $this->photoGate->shouldShowWarningBanner($user));
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
    }
}
