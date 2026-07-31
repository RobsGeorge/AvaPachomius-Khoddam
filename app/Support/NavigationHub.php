<?php

namespace App\Support;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\ServiceApplication;
use App\Models\User;
use App\Services\ApplicationsHubQuery;
use App\Services\CoursePermissionResolver;
use App\Services\RolePreviewService;
use App\Services\RolesHubService;
use App\Services\ServiceContextService;
use App\Services\Structure\StructureAnchorResolver;
use App\Services\StudentRosterService;

class NavigationHub
{
    public static function academicLinks(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $resolver = app(CoursePermissionResolver::class);
        $links = [];

        if (self::canAnyCourse($user, $resolver, ['curriculum.view', 'curriculum.manage'])) {
            $links[] = self::categorized(self::link('curriculum.index', 'nav.curriculum', 'bi-journal-bookmark', ['curriculum.*'], 'curriculum.view', 'curriculum', StructureAnchorResolver::ANCHOR_ENROLLMENT), 'learning');
        }

        if (self::canAnyCourse($user, $resolver, ['curriculum.manage'])) {
            $links[] = self::categorized(self::link('sessions.index', 'nav.sessions', 'bi-calendar3', ['sessions.*'], 'curriculum.manage', 'curriculum', StructureAnchorResolver::ANCHOR_ATTENDANCE), 'learning');
            $links[] = self::categorized(self::link('modules.index', 'nav.modules', 'bi-collection', ['modules.*'], 'curriculum.manage', 'curriculum', StructureAnchorResolver::ANCHOR_ENROLLMENT), 'learning');
        }

        if (self::canAnyCourse($user, $resolver, ['assignment.view', 'assignment.manage'])) {
            $links[] = self::categorized(self::link('assignments.index', 'dashboard.assignments', 'bi-journal-text', [
                'assignments.*',
            ], 'assignment.view', 'assignments', StructureAnchorResolver::ANCHOR_ASSIGNMENT_LEVELS), 'assessment');
        }

        if (self::canAnyCourse($user, $resolver, ['exam.author', 'exam.grade'])) {
            $links[] = self::categorized(self::link('exams.dashboard', 'dashboard.manage_exams', 'bi-patch-check', [
                'exams.dashboard', 'exams.builder', 'exams.grades', 'exams.admin-dashboard',
            ], 'exam.author', 'exams'), 'assessment');
        }

        if (self::canAnyCourse($user, $resolver, ['exam.view', 'exam.take'])) {
            $links[] = self::categorized(self::link('exams.index', 'dashboard.view_exams', 'bi-calendar2-check', [
                'exams.index', 'exams.attempt.*',
            ], 'exam.view', 'exams'), 'assessment');
        }

        if (self::canAnyCourse($user, $resolver, ['attendance.view_all'])) {
            $links[] = self::categorized(self::link('attendance.all', 'nav.attendance', 'bi-calendar-check', [
                'attendance.all', 'attendance.user', 'attendance.by-date', 'attendance.user-report',
            ], 'attendance.view_all', 'attendance', StructureAnchorResolver::ANCHOR_ATTENDANCE), 'people');
            $links[] = self::categorized(self::link('attendance.report', 'dashboard.attendance_report', 'bi-graph-up', ['attendance.report'], 'attendance.report', 'attendance', StructureAnchorResolver::ANCHOR_ATTENDANCE), 'people');
        }

        if (self::canAnyCourse($user, $resolver, ['roster.view'])) {
            $links[] = self::categorized(self::link('students.roster', 'students.roster_title', 'bi-person-lines-fill', ['students.roster', 'students.roster.announce'], 'roster.view', null, StructureAnchorResolver::ANCHOR_ENROLLMENT), 'people');
        }

        if (self::canAnyCourse($user, $resolver, ['announcement.manage'])) {
            $links[] = self::categorized(self::link('announcements.manage.index', 'announcements.manage_title', 'bi-megaphone', ['announcements.manage.*'], 'announcement.manage', 'announcements'), 'people');
        }

        if (self::canAnyCourse($user, $resolver, ['communications.report'])) {
            $links[] = self::categorized(self::link('communications.report', 'communications.nav', 'bi-envelope-paper-heart', [
                'communications.report', 'communications.report.export',
            ], 'communications.report'), 'people');
        }

        if (self::canAnyCourse($user, $resolver, ['graduation.view', 'course.close'])) {
            $links[] = self::categorized(self::link('graduation.index', 'pages.graduation_title', 'bi-mortarboard', ['graduation.*'], 'graduation.view', 'grades'), 'assessment');
        }

        if (self::canAnyCourse($user, $resolver, ['email_templates.manage', 'certificate.manage'])) {
            $course = current_course();
            if ($course) {
                $links[] = self::categorized([
                    'url' => route('courses.email-templates.index', $course),
                    'label' => __('email_templates.nav'),
                    'icon' => 'bi-envelope-paper',
                    'active' => request()->routeIs('courses.email-templates.*'),
                    'permission' => 'email_templates.manage',
                ], 'learning');
            }
        }

        if (self::canAnyCourse($user, $resolver, ['course.view']) && ! self::canAnyCourse($user, $resolver, ['curriculum.manage'])) {
            $links[] = self::categorized(self::link('available-courses.index', 'course_applications.available_courses_title', 'bi-mortarboard', [
                'available-courses.index', 'courses.apply', 'courses.apply.store',
                'courses.application.status', 'courses.application.edit', 'courses.application.update',
            ], 'course.view'), 'learning');
        }

        if (self::canAnyCourse($user, $resolver, ['attendance.view_own']) && ! self::canAnyCourse($user, $resolver, ['attendance.view_all'])) {
            $links[] = self::categorized(self::link('attendance.my', 'nav.my_attendance', 'bi-calendar-check', ['attendance.my'], 'attendance.view_own', 'attendance', StructureAnchorResolver::ANCHOR_ATTENDANCE), 'people');
        }

        if (self::canAnyCourse($user, $resolver, ['roster.view'])) {
            $links[] = self::categorized(self::link('students.birthdays', 'students.birthdays_title', 'bi-cake2', ['students.birthdays'], 'roster.view'), 'people');
        }

        if (self::canAnyCourse($user, $resolver, ['announcement.view']) && ! self::canAnyCourse($user, $resolver, ['announcement.manage'])) {
            $links[] = self::categorized(self::link('announcements.index', 'announcements.title', 'bi-megaphone', [
                'announcements.index', 'announcements.show', 'announcements.dismiss-banner',
            ], 'announcement.view', 'announcements'), 'people');
        }

        if (self::canAnyCourse($user, $resolver, ['feedback.view', 'feedback.manage'])) {
            $links[] = self::categorized(self::link('feedback.index', 'dashboard.feedback', 'bi-chat-square-text', ['feedback.*'], 'feedback.view', 'feedback'), 'assessment');
        }

        if (self::canAnyCourse($user, $resolver, ['live_quiz.play', 'live_quiz.manage'])) {
            $links[] = self::categorized(self::link('live-quiz.index', 'dashboard.live_quiz', 'bi-lightning-charge', ['live-quiz.*'], 'live_quiz.play', 'live_quiz'), 'assessment');
        }

        if (
            $user->canInSystem('events.view')
            || $user->canInSystem('events.reserve')
            || self::canAnyCourse($user, $resolver, ['events.view', 'events.reserve'])
            || $user->isEventAdmin()
        ) {
            $links[] = self::categorized(self::link('events.index', 'dashboard.events', 'bi-calendar-event', [
                'events.index', 'events.show', 'events.my-reservations', 'events.admin.*', 'events.check-in.verify',
            ], 'events.view', 'events'), 'community');
        }

        return self::filterByStructureAnchors(self::filterByCapability($links), $user);
    }

    public static function serviceLinks(?User $user): array
    {
        if (! $user instanceof User || ! ChurchService::tableReady()) {
            return [];
        }

        $links = [];
        $serviceContext = app(ServiceContextService::class);
        $rolesHub = app(RolesHubService::class);
        $roster = app(StudentRosterService::class);
        $current = $serviceContext->currentService($user) ?? current_service();
        $selectable = $serviceContext->selectableServices($user);
        $accessibleRoster = $roster->accessibleServices($user);
        $manageable = $rolesHub->manageableServices($user);

        if ($selectable->isNotEmpty() || ($user->is_superadmin ?? false)) {
            $links[] = array_merge(self::link('services.select', 'service.select_title', 'fas fa-church', [
                'services.select', 'services.select.*',
            ], 'service.view'), ['category' => 'ops']);
        }

        if ($accessibleRoster->isNotEmpty()) {
            $params = $current ? ['service' => $current->service_id] : [];
            $links[] = [
                'url' => route('services.roster', $params),
                'label' => __('service.roster_title'),
                'icon' => 'bi-people',
                'active' => request()->routeIs('services.roster'),
                'permission' => 'service.view',
                'category' => 'ops',
            ];
        }

        if ($user->canInSystem('service_application.review')) {
            $links[] = array_merge(self::link(
                'admin.service-applications.index',
                'service.applications_admin_title',
                'bi-clipboard-check',
                ['admin.service-applications.*'],
                'service_application.review'
            ), ['category' => 'ops']);
        }

        if ($user->canInSystem('platform.service_crud')) {
            $links[] = array_merge(self::link(
                'admin.services.index',
                'service.manage_title',
                'fas fa-church',
                ['admin.services.*'],
                'platform.service_crud'
            ), ['category' => 'admin']);
        }

        if ($manageable->isNotEmpty()) {
            $serviceForHub = $current && $manageable->contains('service_id', $current->service_id)
                ? $current
                : $manageable->first();
            $links[] = [
                'url' => $rolesHub->hubUrl(null, 'service', $serviceForHub),
                'label' => __('rbac.section_service'),
                'icon' => 'bi-shield-check',
                'active' => request()->routeIs('roles.hub') && request()->query('section') === 'service',
                'permission' => 'service.role.manage',
                'category' => 'admin',
            ];
        }

        if ($current instanceof ChurchService) {
            $pending = ServiceApplication::query()
                ->where('user_id', $user->user_id)
                ->where('service_id', $current->service_id)
                ->where('status', ServiceApplication::STATUS_PENDING)
                ->exists();

            $belongs = $selectable->contains('service_id', $current->service_id);

            if ($pending) {
                $links[] = [
                    'url' => route('services.application.status', $current),
                    'label' => __('service.application_status_title'),
                    'icon' => 'bi-hourglass-split',
                    'active' => request()->routeIs('services.application.status'),
                    'permission' => 'service.view',
                    'category' => 'ops',
                ];
            } elseif (! $belongs && ! ($user->is_superadmin ?? false)) {
                $links[] = [
                    'url' => route('services.apply', $current),
                    'label' => __('service.apply_title'),
                    'icon' => 'bi-person-plus',
                    'active' => request()->routeIs('services.apply', 'services.apply.store'),
                    'permission' => 'service.view',
                    'category' => 'ops',
                ];
            }
        }

        // Bound tenant only when MULTI_TENANT=true (console host stays unbound).
        // Fall back to Tenant Zero only while tenancy is dormant (production until cutover).
        $church = \App\Tenancy\TenantContext::current();
        if (! $church && ! config('tenancy.enabled') && \Illuminate\Support\Facades\Schema::hasTable('church')) {
            $church = \App\Models\Church::query()->where('slug', config('tenancy.main_slug'))->first();
        }
        $resolver = app(CoursePermissionResolver::class);
        $churchLinks = [];

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'priest.view', $church)
            || $resolver->canInChurch($user, 'priest.manage', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.priests.index',
                'nav.priests',
                'bi-person-badge',
                ['church.priests.*'],
                'priest.view',
                'church_management'
            ), ['category' => 'pastoral']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'confession.view', $church)
            || $resolver->canInChurch($user, 'confession.manage', $church)
            || $resolver->canInChurch($user, 'confession.manage_delegated', $church)
            || $resolver->canInChurch($user, 'confession.book', $church)
            || $resolver->canInChurch($user, 'confession.book_on_behalf', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.confession.index',
                'nav.confession',
                'bi-calendar2-heart',
                ['church.confession.*'],
                'confession.view',
                'church_management'
            ), ['category' => 'pastoral']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'appointment.view', $church)
            || $resolver->canInChurch($user, 'appointment.manage', $church)
            || $resolver->canInChurch($user, 'appointment.manage_delegated', $church)
            || $resolver->canInChurch($user, 'appointment.book', $church)
            || $resolver->canInChurch($user, 'appointment.book_on_behalf', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.appointments.index',
                'nav.appointments',
                'bi-calendar-check',
                ['church.appointments.*'],
                'appointment.view',
                'church_management'
            ), ['category' => 'pastoral']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'home_visit.view', $church)
            || $resolver->canInChurch($user, 'home_visit.manage', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.home-visits.index',
                'nav.home_visits',
                'bi-house-heart',
                ['church.home-visits.*'],
                'home_visit.view',
                'church_management'
            ), ['category' => 'pastoral']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'church.members.manage', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.members.index',
                'nav.church_members',
                'bi-people',
                ['church.members.*'],
                'church.members.manage',
                'church_management'
            ), ['category' => 'pastoral']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'church.cycle.view', $church)
            || $resolver->canInChurch($user, 'church.cycle.manage', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.cycle.index',
                'nav.church_cycle',
                'bi-calendar2-range',
                ['church.cycle.*'],
                'church.cycle.view',
                'church_management'
            ), ['category' => 'pastoral']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'public_site.profile', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.public-profile.edit',
                'nav.public_profile',
                'bi-building',
                ['church.public-profile.*'],
                'public_site.profile',
                'public_site'
            ), ['category' => 'public_site']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'public_site.theme', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.branding.edit',
                'nav.church_branding',
                'bi-palette',
                ['church.branding.*'],
                'public_site.theme',
                'public_site'
            ), ['category' => 'public_site']);

            $churchLinks[] = array_merge(self::link(
                'church.event-theme.edit',
                'nav.event_theme',
                'bi-calendar-heart',
                ['church.event-theme.*'],
                'public_site.theme',
                'public_site'
            ), ['category' => 'public_site']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'public_site.manage', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'site.homepage.edit',
                'nav.homepage_cms',
                'bi-house-door',
                ['site.homepage.*', 'site.preview', 'site.media.*'],
                'public_site.manage',
                'public_site'
            ), ['category' => 'public_site']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'finance.payroll.view', $church)
            || $resolver->canInChurch($user, 'finance.payroll.manage', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.finance.payroll.index',
                'nav.payroll',
                'bi-cash-stack',
                ['church.finance.payroll.*'],
                'finance.payroll.view',
                'church_management'
            ), ['category' => 'finance']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'finance.money_in.view', $church)
            || $resolver->canInChurch($user, 'finance.money_in.manage', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.finance.money-in.index',
                'nav.money_in',
                'bi-wallet2',
                ['church.finance.money-in.*'],
                'finance.money_in.view',
                'church_management'
            ), ['category' => 'finance']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'church.observability.view', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'admin.observability.index',
                'nav.observability',
                'bi-activity',
                ['admin.observability.*'],
                'church.observability.view',
                'church_management'
            ), ['category' => 'config']);
        }

        return array_merge($links, self::filterByCapability($churchLinks));
    }

    public static function systemLinks(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $links = [];
        $hub = app(RolesHubService::class);

        if ($hub->canAccess($user)) {
            $course = current_course();
            $links[] = self::categorized([
                'url' => $hub->hubUrl(
                    $course && $hub->manageableCourses($user)->contains('course_id', $course->course_id)
                        ? $course
                        : null
                ),
                'label' => __('rbac.hub_title'),
                'icon' => 'bi-shield-check',
                'active' => request()->routeIs(
                    'roles.hub',
                    'courses.roles.*',
                    'user-course-roles.*',
                    'roles.index',
                    'roles.store',
                    'roles.destroy',
                    'superadmin.course-roles',
                    'superadmin.templates.*',
                    'superadmin.system-roles.*',
                    'superadmin.group-visibility.*',
                ),
                'permission' => 'system.role.manage',
            ], 'access');
        }

        if ($user->canInSystem('translation.manage')) {
            $links[] = self::categorized(self::link('admin.translations.index', 'nav.translations', 'bi-translate', ['admin.translations.*'], 'translation.manage'), 'config');
        }

        if ($user->canInSystem('attendance.configure')) {
            $links[] = self::categorized(self::link('admin.attendance-settings.edit', 'pages.attendance_settings_title', 'bi-sliders', ['admin.attendance-settings.*'], 'attendance.configure'), 'config');
        }

        if ($user->canInSystem('profile_photo.review')) {
            $links[] = self::categorized(self::link('admin.profile-photos.index', 'profile_photos.report_title', 'bi-person-badge', ['admin.profile-photos.*'], 'profile_photo.review'), 'reviews');
        }

        if ($user->canInSystem('registration.review')) {
            $links[] = self::categorized(self::link('admin.registration-applications.index', 'registration_review.queue_title', 'bi-clipboard-check', ['admin.registration-applications.*'], 'registration.review'), 'reviews');
        }

        if ($user->canInSystem('people.view') || $user->canInSystem('church.members.manage')) {
            $links[] = self::categorized(self::link('people.index', 'people_onboarding.nav', 'bi-people', ['people.*'], 'people.view'), 'people');
        }

        if ($user->canAccessAdminCourseApplicationForms()) {
            $links[] = self::categorized(self::link('admin.courses.application-forms.index', 'course_applications.builder_index_title', 'bi-ui-checks', ['admin.courses.application-forms.*', 'admin.courses.application-form.*'], 'course_application.form_builder'), 'reviews');
        }

        if (app(ApplicationsHubQuery::class)->canAccessHub($user)) {
            $links[] = self::categorized(self::link('admin.applications-hub.index', 'applications_hub.nav_title', 'bi-inboxes', ['admin.applications-hub.*'], 'applications.hub.view'), 'reviews');
        }

        if ($user->canAccessAdminCourseApplications()) {
            $links[] = self::categorized(self::link('admin.course-applications.index', 'course_applications.queue_title', 'bi-journal-check', ['admin.course-applications.*'], 'course_application.review'), 'reviews');
        }

        if ($user->canInSystem('graduation.settings')) {
            $links[] = self::categorized(self::link('admin.graduation-settings.index', 'pages.graduation_configure_criteria', 'bi-award', ['admin.graduation-settings.*'], 'graduation.settings'), 'config');
        }

        if ($user->isAdmin() && empty($links)) {
            return self::legacySystemLinks($user);
        }

        return $links;
    }

    /** @return array<int, array{title: string, links: array<int, array<string, mixed>>}> */
    public static function superadminSections(?User $user): array
    {
        if (! self::hasSuperadmin($user)) {
            return [];
        }

        $exclusiveLinks = [
            self::hubLink('superadmin.churches.index', 'tenancy.nav_churches', 'tenancy.nav_churches_desc', 'bi-building', ['superadmin.churches.*'], true),
            self::hubLink('superadmin.church-applications.index', 'church_applications.nav_title', 'church_applications.nav_desc', 'bi-building-add', ['superadmin.church-applications.*'], true),
            self::hubLink('admin.applications-hub.index', 'applications_hub.nav_title', 'applications_hub.nav_desc', 'bi-inboxes', ['admin.applications-hub.*'], true),
            self::hubLink('superadmin.plans.index', 'billing.nav_plans', 'billing.nav_plans_desc', 'bi-credit-card', ['superadmin.plans.*'], true),
            self::hubLink('superadmin.people.merge.index', 'people.nav_merge', 'people.nav_merge_desc', 'bi-people', ['superadmin.people.*'], true),
            self::hubLink('superadmin.courses', 'pages.manage_services_and_courses', 'pages.superadmin_services_and_courses_desc', 'bi-journal-bookmark-fill', ['superadmin.courses'], true),
            self::hubLink('roles.hub', 'rbac.hub_title', 'rbac.hub_intro', 'bi-shield-check', [
                'roles.hub',
                'courses.roles.*',
                'user-course-roles.*',
                'superadmin.course-roles',
                'superadmin.templates.*',
                'superadmin.system-roles.*',
                'superadmin.group-visibility.*',
            ], true),
            self::hubLink('superadmin.event-admins', 'events.event_admins_title', 'events.event_admins_hint', 'bi-calendar-event', ['superadmin.event-admins', 'superadmin.event-admins.*'], true),
            self::hubLink('superadmin.security', 'pages.superadmin_security_title', 'pages.superadmin_security_desc', 'bi-shield-lock', ['superadmin.security', 'superadmin.sessions.*', 'superadmin.impersonate', 'superadmin.role-preview'], true),
            self::hubLink('superadmin.audit.index', 'nav.audit_reports', 'pages.superadmin_audit_desc', 'bi-journal-text', ['superadmin.audit.*'], true),
            self::hubLink('superadmin.observability.index', 'nav.observability', 'pages.superadmin_observability_desc', 'bi-activity', ['superadmin.observability.*'], true),
            self::hubLink('superadmin.events.tests.index', 'nav.events_tests', 'pages.superadmin_events_tests_desc', 'bi-bug', ['superadmin.events.tests.*'], true),
            self::hubLink('superadmin.system-tests.index', 'nav.system_tests', 'pages.superadmin_system_tests_desc', 'bi-clipboard2-check', ['superadmin.system-tests.*'], true),
            self::hubLink('superadmin.scheduled-tasks.index', 'nav.scheduled_tasks', 'pages.superadmin_scheduled_tasks_desc', 'bi-clock-history', ['superadmin.scheduled-tasks.*'], true),
        ];

        return [
            [
                'title' => __('pages.superadmin_section_exclusive'),
                'links' => $exclusiveLinks,
            ],
        ];
    }

    public static function superadminLinks(?User $user): array
    {
        $links = [];
        foreach (self::superadminSections($user) as $section) {
            foreach ($section['links'] as $link) {
                $links[] = $link;
            }
        }

        return $links;
    }

    public static function hasSuperadmin(?User $user): bool
    {
        if (! $user instanceof User || ! ($user->is_superadmin ?? false)) {
            return false;
        }

        // View-as masks platform nav; platform-enter keeps full superadmin chrome.
        return ! RolePreviewService::isActive();
    }

    public static function isSuperadminActive(?User $user): bool
    {
        if (! self::hasSuperadmin($user)) {
            return false;
        }

        if (request()->routeIs('superadmin.*', 'hubs.superadmin')) {
            return true;
        }

        return self::anyActive(self::superadminLinks($user));
    }

    public static function hasSystem(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return count(self::systemLinks($user)) > 0;
    }

    public static function hasService(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return count(self::serviceLinks($user)) > 0;
    }

    public static function isServiceActive(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (request()->routeIs(
            'hubs.service',
            'services.select',
            'services.select.*',
            'services.roster',
            'services.apply',
            'services.apply.store',
            'services.application.status',
            'admin.service-applications.*',
            'admin.services.*',
            'church.priests.*',
            'church.confession.*',
            'church.appointments.*',
            'church.home-visits.*',
            'church.members.*',
            'church.cycle.*',
            'church.finance.*',
            'church.public-profile.*',
            'church.branding.*',
            'church.event-theme.*',
            'site.homepage.*',
            'site.preview',
            'site.media.*',
        )) {
            return true;
        }

        if (request()->routeIs('roles.hub') && request()->query('section') === 'service') {
            return true;
        }

        return self::anyActive(self::serviceLinks($user));
    }

    public static function isAcademicActive(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (request()->routeIs('hubs.academic')) {
            return true;
        }

        return self::anyActive(self::academicLinks($user));
    }

    public static function isSystemActive(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (request()->routeIs('hubs.system')) {
            return true;
        }

        return self::anyActive(self::systemLinks($user));
    }

    /**
     * Ordered hub/nav section titles for a link list. Empty sections are omitted by
     * {@see groupedSections()}.
     *
     * @return array<string, string> category key => localized title
     */
    public static function academicSectionDefinitions(): array
    {
        return [
            'learning' => __('nav.hub_section_learning'),
            'assessment' => __('nav.hub_section_assessment'),
            'people' => __('nav.hub_section_people'),
            'community' => __('nav.hub_section_community'),
        ];
    }

    /** @return array<string, string> */
    public static function serviceSectionDefinitions(): array
    {
        return [
            'ops' => __('service.hub_section_ops'),
            'admin' => __('service.hub_section_admin'),
            'pastoral' => __('service.hub_section_pastoral'),
            'finance' => __('service.hub_section_finance'),
            'public_site' => __('service.hub_section_public_site'),
        ];
    }

    /** @return array<string, string> */
    public static function systemSectionDefinitions(): array
    {
        return [
            'access' => __('nav.hub_section_access'),
            'reviews' => __('nav.hub_section_reviews'),
            'config' => __('nav.hub_section_config'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     * @param  array<string, string>  $definitions
     * @return array<int, array{key: string, title: string, links: array<int, array<string, mixed>>}>
     */
    public static function groupedSections(array $links, array $definitions): array
    {
        $defaultKey = array_key_first($definitions) ?: 'ops';
        $grouped = collect($links)->groupBy(fn (array $link) => $link['category'] ?? $defaultKey);
        $sections = [];

        foreach ($definitions as $key => $title) {
            $items = ($grouped->get($key) ?? collect())->values()->all();
            if ($items === []) {
                continue;
            }
            $sections[] = [
                'key' => $key,
                'title' => $title,
                'links' => $items,
            ];
        }

        foreach ($grouped as $key => $items) {
            if (array_key_exists($key, $definitions)) {
                continue;
            }
            $list = $items->values()->all();
            if ($list === []) {
                continue;
            }
            $sections[] = [
                'key' => (string) $key,
                'title' => is_string($key) ? ucfirst(str_replace('_', ' ', $key)) : (string) $key,
                'links' => $list,
            ];
        }

        return $sections;
    }

    /** @param  array<string, mixed>  $link */
    protected static function categorized(array $link, string $category): array
    {
        $link['category'] = $category;

        return $link;
    }

    protected static function hubLink(string $routeName, string $labelKey, string $descKey, string $icon, array $patterns, bool $superadminOnly = false): array
    {
        $link = self::link($routeName, $labelKey, $icon, $patterns);
        $link['description'] = __($descKey);
        $link['superadmin_only'] = $superadminOnly;

        return $link;
    }

    protected static function link(string $routeName, string $labelKey, string $icon, array $patterns, ?string $permission = null, ?string $capability = null, ?string $structureAnchor = null): array
    {
        $course = current_course();
        if ($course && $routeName === 'curriculum.index') {
            return [
                'url' => route('curriculum.show', $course->course_id),
                'label' => __($labelKey),
                'icon' => $icon,
                'active' => request()->routeIs(...$patterns)
                    || request()->routeIs('curriculum.show', 'curriculum.admin'),
                'permission' => $permission,
                'capability' => $capability,
                'structure_anchor' => $structureAnchor,
            ];
        }

        if ($course && $routeName === 'graduation.index') {
            return [
                'url' => route('graduation.show', $course->course_id),
                'label' => __($labelKey),
                'icon' => $icon,
                'active' => request()->routeIs('graduation.show', 'graduation.export', 'graduation.*'),
                'permission' => $permission,
                'capability' => $capability,
                'structure_anchor' => $structureAnchor,
            ];
        }

        return [
            'url' => route($routeName),
            'label' => __($labelKey),
            'icon' => $icon,
            'active' => request()->routeIs(...$patterns),
            'permission' => $permission,
            'capability' => $capability,
            'structure_anchor' => $structureAnchor,
        ];
    }

    /**
     * T2 — drop links whose capability is disabled for the currently-bound church.
     * When no church is bound (tenancy dormant), every link is kept, so nav is unchanged
     * in production until the T7 cutover.
     *
     * @param  array<int, array<string, mixed>>  $links
     * @return array<int, array<string, mixed>>
     */
    protected static function filterByCapability(array $links): array
    {
        $church = \App\Tenancy\TenantContext::current();
        if ($church === null) {
            return $links;
        }

        return array_values(array_filter($links, function (array $link) use ($church) {
            $capability = $link['capability'] ?? null;

            return $capability === null || $church->hasCapability($capability);
        }));
    }

    /**
     * T8b — incremental: when the current service has a structure template, hide links
     * whose required anchor is absent. Untagged links and services without a template
     * stay visible (backward compatible).
     *
     * @param  array<int, array<string, mixed>>  $links
     * @return array<int, array<string, mixed>>
     */
    protected static function filterByStructureAnchors(array $links, User $user): array
    {
        $service = app(ServiceContextService::class)->currentService($user) ?? current_service();
        if (! $service instanceof ChurchService || ! $service->structure_template_id) {
            return $links;
        }

        $resolver = app(StructureAnchorResolver::class);
        $hasEnrollment = filled($resolver->enrollmentLevel($service));
        $hasAttendance = filled($resolver->attendanceLevel($service));
        $hasAssignments = $resolver->assignmentLevels($service) !== [];

        return array_values(array_filter($links, function (array $link) use ($hasEnrollment, $hasAttendance, $hasAssignments) {
            $anchor = $link['structure_anchor'] ?? null;
            if ($anchor === null) {
                return true;
            }

            return match ($anchor) {
                StructureAnchorResolver::ANCHOR_ENROLLMENT => $hasEnrollment,
                StructureAnchorResolver::ANCHOR_ATTENDANCE => $hasAttendance,
                StructureAnchorResolver::ANCHOR_ASSIGNMENT_LEVELS => $hasAssignments,
                default => true,
            };
        }));
    }

    public static function activePageIcon(?User $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        $activeLinks = array_values(array_filter(
            self::allNavLinks($user),
            static fn (array $link): bool => ! empty($link['active']) && ! empty($link['icon'])
        ));

        if ($activeLinks === []) {
            return null;
        }

        $specificLinks = array_values(array_filter(
            $activeLinks,
            static fn (array $link): bool => ! self::isGenericHubLink($link)
        ));

        $chosen = $specificLinks[0] ?? $activeLinks[0];

        return is_string($chosen['icon'] ?? null) ? $chosen['icon'] : null;
    }

    /** @return array<int, array<string, mixed>> */
    protected static function allNavLinks(User $user): array
    {
        return array_merge(
            self::academicLinks($user),
            self::serviceLinks($user),
            self::systemLinks($user),
            self::superadminLinks($user),
        );
    }

    protected static function isGenericHubLink(array $link): bool
    {
        static $hubUrls = null;

        if ($hubUrls === null) {
            $hubUrls = array_values(array_filter([
                parse_url(route('hubs.academic', [], false), PHP_URL_PATH),
                parse_url(route('hubs.service', [], false), PHP_URL_PATH),
                parse_url(route('hubs.system', [], false), PHP_URL_PATH),
                parse_url(route('superadmin.index', [], false), PHP_URL_PATH),
            ]));
        }

        $url = $link['url'] ?? null;
        if (! is_string($url) || $url === '') {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return in_array($path, $hubUrls, true);
    }

    protected static function anyActive(array $links): bool
    {
        foreach ($links as $link) {
            if ($link['active']) {
                return true;
            }
        }

        return false;
    }

    private static function canAnyCourse(User $user, CoursePermissionResolver $resolver, array $permissions): bool
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return true;
        }

        foreach ($permissions as $perm) {
            if ($user->canInSystem($perm)) {
                return true;
            }
        }

        if (RolePreviewService::isActive()) {
            if (RolePreviewService::isGeneral()) {
                return false;
            }

            $course = RolePreviewService::previewCourse();

            return $course instanceof Course
                && $resolver->canAnyInCourse($user, $permissions, $course);
        }

        foreach ($user->userCourseRoles()->activeStaff()->pluck('course_id') as $courseId) {
            $course = Course::find($courseId);
            if ($course && $resolver->canAnyInCourse($user, $permissions, $course)) {
                return true;
            }
        }

        if ($user->isInstructorOrAdmin()) {
            return true;
        }

        return false;
    }

    /** Fallback while migrating legacy admin users without system role grants. */
    private static function legacySystemLinks(User $user): array
    {
        $links = [];
        if ($user->isAdmin()) {
            $hub = app(RolesHubService::class);
            $links[] = self::categorized([
                'url' => $hub->hubUrl(),
                'label' => __('rbac.hub_title'),
                'icon' => 'bi-shield-check',
                'active' => request()->routeIs('roles.hub', 'user-course-roles.*', 'roles.*', 'courses.roles.*'),
            ], 'access');
            $links[] = self::categorized(self::link('admin.translations.index', 'nav.translations', 'bi-translate', ['admin.translations.*']), 'config');
            $links[] = self::categorized(self::link('admin.attendance-settings.edit', 'pages.attendance_settings_title', 'bi-sliders', ['admin.attendance-settings.*']), 'config');
            $links[] = self::categorized(self::link('admin.profile-photos.index', 'profile_photos.report_title', 'bi-person-badge', ['admin.profile-photos.*']), 'reviews');
            $links[] = self::categorized(self::link('admin.registration-applications.index', 'registration_review.queue_title', 'bi-clipboard-check', ['admin.registration-applications.*']), 'reviews');
            $links[] = self::categorized(self::link('admin.applications-hub.index', 'applications_hub.nav_title', 'bi-inboxes', ['admin.applications-hub.*']), 'reviews');
            $links[] = self::categorized(self::link('admin.courses.application-forms.index', 'course_applications.builder_index_title', 'bi-ui-checks', ['admin.courses.application-forms.*', 'admin.courses.application-form.*']), 'reviews');
            $links[] = self::categorized(self::link('admin.course-applications.index', 'course_applications.queue_title', 'bi-journal-check', ['admin.course-applications.*']), 'reviews');
            $links[] = self::categorized(self::link('admin.graduation-settings.index', 'pages.graduation_configure_criteria', 'bi-award', ['admin.graduation-settings.*']), 'config');
        }

        return $links;
    }
}
