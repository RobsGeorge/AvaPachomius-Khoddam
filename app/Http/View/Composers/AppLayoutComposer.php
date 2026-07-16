<?php

namespace App\Http\View\Composers;

use App\Services\AnnouncementService;
use App\Services\CourseContextService;
use App\Services\NotificationFeedService;
use App\Services\ProfilePhotoGateService;
use App\Services\RegistrationApplicationService;
use App\Services\ServiceContextService;
use App\Services\StudentOnboardingService;
use App\Models\Church;
use App\Models\ChurchService;
use App\Models\RegistrationApplication;
use App\Support\ChurchHost;
use App\Tenancy\TenantContext;
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

        $serviceReady = ChurchService::tableReady();
        $currentService = $serviceReady ? (current_service() ?? $this->serviceContext->currentService($user)) : null;
        $requiresServiceContext = $serviceReady && $this->serviceContext->requiresServiceContext($user);
        $serviceCapability = $serviceReady && (
            $requiresServiceContext
            || $this->serviceContext->supportsOptionalServiceContext($user)
        );
        $selectableServices = $serviceCapability
            ? $this->serviceContext->selectableServices($user)
            : collect();

        // Members: switcher only when multiple. Superadmin in system-wide mode keeps
        // a picker when at least one option exists so they can enter that context.
        $isSuper = (bool) ($user->is_superadmin ?? false);
        $supportsServiceSwitcher = $serviceCapability && (
            $selectableServices->count() > 1
            || ($isSuper && $selectableServices->isNotEmpty() && ! $currentService)
        );
        $showServiceContextLabel = (bool) $currentService && ! $supportsServiceSwitcher;

        // Church switcher (T4): host-based links, only when MULTI_TENANT is on.
        $tenancyOn = (bool) config('tenancy.enabled');
        $currentChurch = TenantContext::current();
        $selectableChurches = collect();
        if ($tenancyOn) {
            $selectableChurches = $isSuper
                ? Church::query()->where('status', 'active')->orderBy('name')->get()
                : $user->churches()->where('church.status', 'active')->orderBy('church.name')->get();
        }
        $supportsChurchSwitcher = $tenancyOn && (
            $selectableChurches->count() > 1
            || ($isSuper && $selectableChurches->isNotEmpty())
        );
        $showChurchContextLabel = $tenancyOn && (bool) $currentChurch && ! $supportsChurchSwitcher;

        $currentCourse = current_course() ?? $this->courseContext->currentCourse($user);
        $requiresCourseContext = $this->courseContext->requiresCourseContext($user);
        $courseCapability = $requiresCourseContext
            || $this->courseContext->supportsOptionalCourseContext($user);
        $selectableCourses = $courseCapability
            ? $this->courseContext->selectableCourses($user)
            : collect();

        $supportsCourseSwitcher = $courseCapability && (
            $selectableCourses->count() > 1
            || ($isSuper && $selectableCourses->isNotEmpty() && ! $currentCourse)
        );
        $showCourseContextLabel = (bool) $currentCourse && ! $supportsCourseSwitcher;

        $view->with('currentService', $currentService);
        $view->with('requiresServiceContext', $requiresServiceContext);
        $view->with('supportsServiceSwitcher', $supportsServiceSwitcher);
        $view->with('showServiceContextLabel', $showServiceContextLabel);
        $view->with('selectableServices', $selectableServices);

        $view->with('currentChurch', $currentChurch);
        $view->with('supportsChurchSwitcher', $supportsChurchSwitcher);
        $view->with('showChurchContextLabel', $showChurchContextLabel);
        $view->with('selectableChurches', $selectableChurches);
        $view->with('isConsoleHost', ChurchHost::isConsoleHost());

        $view->with('currentCourse', $currentCourse);
        $view->with('requiresCourseContext', $requiresCourseContext);
        $view->with('supportsCourseSwitcher', $supportsCourseSwitcher);
        $view->with('showCourseContextLabel', $showCourseContextLabel);
        $view->with('isSystemWideMode', $this->courseContext->isSystemWideMode($user));
        $view->with('selectableCourses', $selectableCourses);
        $view->with('instituteName', __('app.institute_name'));
        $view->with('courseBrandingCss', $this->courseContext->brandingCss($currentCourse));
    }
}
