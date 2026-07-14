<?php

namespace App\Http\View\Composers;

use App\Services\AnnouncementService;
use App\Services\CourseContextService;
use App\Services\NotificationFeedService;
use App\Services\ProfilePhotoGateService;
use App\Services\RegistrationApplicationService;
use App\Services\ServiceContextService;
use App\Services\StudentOnboardingService;
use App\Models\ChurchService;
use App\Models\RegistrationApplication;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AppLayoutComposer
{
    public function __construct(
        private ProfilePhotoGateService $photoGate,
        private AnnouncementService $announcements,
        private StudentOnboardingService $onboarding,
        private RegistrationApplicationService $applications,
        private NotificationFeedService $notifications,
        private CourseContextService $courseContext,
        private ServiceContextService $serviceContext,
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

        if (Schema::hasColumn('user', 'application_status') && ! $this->applications->isApproved($user) && ! $user->isAdmin() && ! $user->is_superadmin) {
            $view->with('applicationReviewBanner', match ($user->application_status) {
                RegistrationApplication::STATUS_NEEDS_CORRECTION => 'correction',
                RegistrationApplication::STATUS_REJECTED => 'rejected',
                default => 'pending',
            });
        } else {
            $view->with('applicationReviewBanner', null);
        }

        if ($user->isStudent()) {
            $view->with('unreadAnnouncementCount', $this->announcements->unreadCount($user));
            $view->with('activeAnnouncementBanners', $this->announcements->activeBanners($user));
        } else {
            $view->with('unreadAnnouncementCount', 0);
            $view->with('activeAnnouncementBanners', collect());
        }

        $view->with('unreadNotificationCount', $this->notifications->unreadCount($user));

        if ($this->onboarding->shouldShow($user)) {
            $locale = $this->onboarding->localeForWizard();
            $view->with('showStudentOnboarding', true);
            $view->with('studentOnboardingSteps', $this->onboarding->steps($locale));
            $view->with('studentOnboardingLocale', $locale);
        } else {
            $view->with('showStudentOnboarding', false);
        }

        $currentCourse = current_course() ?? $this->courseContext->currentCourse($user);
        $requiresCourseContext = $this->courseContext->requiresCourseContext($user);
        $supportsCourseSwitcher = $requiresCourseContext
            || $this->courseContext->supportsOptionalCourseContext($user);

        $view->with('currentCourse', $currentCourse);
        $view->with('requiresCourseContext', $requiresCourseContext);
        $view->with('supportsCourseSwitcher', $supportsCourseSwitcher);
        $view->with('isSystemWideMode', $this->courseContext->isSystemWideMode($user));
        $view->with('selectableCourses', $supportsCourseSwitcher
            ? $this->courseContext->selectableCourses($user)
            : collect());
        $view->with('instituteName', __('app.institute_name'));
        $view->with('courseBrandingCss', $this->courseContext->brandingCss($currentCourse));

        $serviceReady = ChurchService::tableReady();
        $currentService = $serviceReady ? (current_service() ?? $this->serviceContext->currentService($user)) : null;
        $requiresServiceContext = $serviceReady && $this->serviceContext->requiresServiceContext($user);
        $supportsServiceSwitcher = $serviceReady && (
            $requiresServiceContext
            || $this->serviceContext->supportsOptionalServiceContext($user)
        );
        $selectableServices = $supportsServiceSwitcher
            ? $this->serviceContext->selectableServices($user)
            : collect();

        // Superadmins may want the switcher even with zero services; members only when selectable.
        if ($supportsServiceSwitcher && ! ($user->is_superadmin ?? false) && $selectableServices->isEmpty()) {
            $supportsServiceSwitcher = false;
        }

        $view->with('currentService', $currentService);
        $view->with('requiresServiceContext', $requiresServiceContext);
        $view->with('supportsServiceSwitcher', $supportsServiceSwitcher);
        $view->with('selectableServices', $selectableServices);
    }
}
