<?php

namespace App\Support;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\ServiceApplication;
use App\Models\User;
use App\Services\CoursePermissionResolver;
use App\Services\RolePreviewService;
use App\Services\RolesHubService;
use App\Services\ServiceContextService;
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
            $links[] = self::link('curriculum.index', 'nav.curriculum', 'bi-journal-bookmark', ['curriculum.*'], 'curriculum.view', 'curriculum');
        }

        if (self::canAnyCourse($user, $resolver, ['curriculum.manage'])) {
            $links[] = self::link('sessions.index', 'nav.sessions', 'bi-calendar3', ['sessions.*'], 'curriculum.manage', 'curriculum');
            $links[] = self::link('modules.index', 'nav.modules', 'bi-collection', ['modules.*'], 'curriculum.manage', 'curriculum');
        }

        if (self::canAnyCourse($user, $resolver, ['assignment.view', 'assignment.manage'])) {
            $links[] = self::link('assignments.index', 'dashboard.assignments', 'bi-journal-text', [
                'assignments.*',
            ], 'assignment.view', 'assignments');
        }

        if (self::canAnyCourse($user, $resolver, ['exam.author', 'exam.grade'])) {
            $links[] = self::link('exams.dashboard', 'dashboard.manage_exams', 'bi-patch-check', [
                'exams.dashboard', 'exams.builder', 'exams.grades', 'exams.admin-dashboard',
            ], 'exam.author', 'exams');
        }

        if (self::canAnyCourse($user, $resolver, ['exam.view', 'exam.take'])) {
            $links[] = self::link('exams.index', 'dashboard.view_exams', 'bi-calendar2-check', [
                'exams.index', 'exams.attempt.*',
            ], 'exam.view', 'exams');
        }

        if (self::canAnyCourse($user, $resolver, ['attendance.view_all'])) {
            $links[] = self::link('attendance.all', 'nav.attendance', 'bi-calendar-check', [
                'attendance.all', 'attendance.user', 'attendance.by-date', 'attendance.user-report',
            ], 'attendance.view_all', 'attendance');
            $links[] = self::link('attendance.report', 'dashboard.attendance_report', 'bi-graph-up', ['attendance.report'], 'attendance.report', 'attendance');
        }

        if (self::canAnyCourse($user, $resolver, ['roster.view'])) {
            $links[] = self::link('students.roster', 'students.roster_title', 'bi-person-lines-fill', ['students.roster', 'students.roster.announce'], 'roster.view');
        }

        if (self::canAnyCourse($user, $resolver, ['announcement.manage'])) {
            $links[] = self::link('announcements.manage.index', 'announcements.manage_title', 'bi-megaphone', ['announcements.manage.*'], 'announcement.manage', 'announcements');
        }

        if (self::canAnyCourse($user, $resolver, ['communications.report'])) {
            $links[] = self::link('communications.report', 'communications.nav', 'bi-envelope-paper-heart', [
                'communications.report', 'communications.report.export',
            ], 'communications.report');
        }

        if (self::canAnyCourse($user, $resolver, ['graduation.view', 'course.close'])) {
            $links[] = self::link('graduation.index', 'pages.graduation_title', 'bi-mortarboard', ['graduation.*'], 'graduation.view', 'grades');
        }

        if (self::canAnyCourse($user, $resolver, ['email_templates.manage', 'certificate.manage'])) {
            $course = current_course();
            if ($course) {
                $links[] = [
                    'url' => route('courses.email-templates.index', $course),
                    'label' => __('email_templates.nav'),
                    'icon' => 'bi-envelope-paper',
                    'active' => request()->routeIs('courses.email-templates.*'),
                    'permission' => 'email_templates.manage',
                ];
            }
        }

        if (self::canAnyCourse($user, $resolver, ['course.view']) && ! self::canAnyCourse($user, $resolver, ['curriculum.manage'])) {
            $links[] = self::link('available-courses.index', 'course_applications.available_courses_title', 'bi-mortarboard', [
                'available-courses.index', 'courses.apply', 'courses.apply.store',
                'courses.application.status', 'courses.application.edit', 'courses.application.update',
            ], 'course.view');
        }

        if (self::canAnyCourse($user, $resolver, ['attendance.view_own']) && ! self::canAnyCourse($user, $resolver, ['attendance.view_all'])) {
            $links[] = self::link('attendance.my', 'nav.my_attendance', 'bi-calendar-check', ['attendance.my'], 'attendance.view_own', 'attendance');
        }

        if (self::canAnyCourse($user, $resolver, ['roster.view'])) {
            $links[] = self::link('students.birthdays', 'students.birthdays_title', 'bi-cake2', ['students.birthdays'], 'roster.view');
        }

        if (self::canAnyCourse($user, $resolver, ['announcement.view']) && ! self::canAnyCourse($user, $resolver, ['announcement.manage'])) {
            $links[] = self::link('announcements.index', 'announcements.title', 'bi-megaphone', [
                'announcements.index', 'announcements.show', 'announcements.dismiss-banner',
            ], 'announcement.view', 'announcements');
        }

        if (self::canAnyCourse($user, $resolver, ['feedback.view', 'feedback.manage'])) {
            $links[] = self::link('feedback.index', 'dashboard.feedback', 'bi-chat-square-text', ['feedback.*'], 'feedback.view', 'feedback');
        }

        if (self::canAnyCourse($user, $resolver, ['live_quiz.play', 'live_quiz.manage'])) {
            $links[] = self::link('live-quiz.index', 'dashboard.live_quiz', 'bi-lightning-charge', ['live-quiz.*'], 'live_quiz.play', 'live_quiz');
        }

        if (
            $user->canInSystem('events.view')
            || $user->canInSystem('events.reserve')
            || self::canAnyCourse($user, $resolver, ['events.view', 'events.reserve'])
            || $user->isEventAdmin()
        ) {
            $links[] = self::link('events.index', 'dashboard.events', 'bi-calendar-event', [
                'events.index', 'events.show', 'events.my-reservations', 'events.admin.*', 'events.check-in.verify',
            ], 'events.view', 'events');
        }

        return self::filterByCapability($links);
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
                    'permission' => 'service_application.form_builder',
                    'category' => 'ops',
                ];
            }
        }

        $church = \App\Tenancy\TenantContext::current()
            ?? (\Illuminate\Support\Facades\Schema::hasTable('church')
                ? \App\Models\Church::query()->where('slug', config('tenancy.main_slug'))->first()
                : null);
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
            ), ['category' => 'church']);
        }

        if ($church && (
            ($user->is_superadmin ?? false)
            || $resolver->canInChurch($user, 'confession.view', $church)
            || $resolver->canInChurch($user, 'confession.manage', $church)
            || $resolver->canInChurch($user, 'confession.book', $church)
        )) {
            $churchLinks[] = array_merge(self::link(
                'church.confession.index',
                'nav.confession',
                'bi-calendar2-heart',
                ['church.confession.*'],
                'confession.view',
                'church_management'
            ), ['category' => 'church']);
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
            ), ['category' => 'church']);
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
            ), ['category' => 'church']);
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
            ), ['category' => 'church']);
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
            $links[] = [
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
            ];
        }

        if ($user->canInSystem('translation.manage')) {
            $links[] = self::link('admin.translations.index', 'nav.translations', 'bi-translate', ['admin.translations.*'], 'translation.manage');
        }

        if ($user->canInSystem('attendance.configure')) {
            $links[] = self::link('admin.attendance-settings.edit', 'pages.attendance_settings_title', 'bi-sliders', ['admin.attendance-settings.*'], 'attendance.configure');
        }

        if ($user->canInSystem('profile_photo.review')) {
            $links[] = self::link('admin.profile-photos.index', 'profile_photos.report_title', 'bi-person-badge', ['admin.profile-photos.*'], 'profile_photo.review');
        }

        if ($user->canInSystem('registration.review')) {
            $links[] = self::link('admin.registration-applications.index', 'registration_review.queue_title', 'bi-clipboard-check', ['admin.registration-applications.*'], 'registration.review');
        }

        if ($user->canInSystem('course_application.form_builder')) {
            $links[] = self::link('admin.courses.application-forms.index', 'course_applications.builder_index_title', 'bi-ui-checks', ['admin.courses.application-forms.*', 'admin.courses.application-form.*'], 'course_application.form_builder');
        }

        if ($user->canInSystem('course_application.review')) {
            $links[] = self::link('admin.course-applications.index', 'course_applications.queue_title', 'bi-journal-check', ['admin.course-applications.*'], 'course_application.review');
        }

        if ($user->canInSystem('graduation.settings')) {
            $links[] = self::link('admin.graduation-settings.index', 'pages.graduation_configure_criteria', 'bi-award', ['admin.graduation-settings.*'], 'graduation.settings');
        }

        if ($user->isAdmin() && empty($links)) {
            return self::legacySystemLinks($user);
        }

        return $links;
    }

    /** @return array<int, array{title: string, links: array<int, array<string, mixed>>}> */
    public static function superadminSections(?User $user): array
    {
        if (! $user?->is_superadmin) {
            return [];
        }

        $currentCourse = current_course();
        $hub = app(RolesHubService::class);

        $exclusiveLinks = [
            self::hubLink('superadmin.index', 'nav.superadmin', 'pages.superadmin_hub_desc', 'bi-shield-lock-fill', ['superadmin.index'], true),
            self::hubLink('superadmin.churches.index', 'tenancy.nav_churches', 'tenancy.nav_churches_desc', 'bi-building', ['superadmin.churches.*'], true),
            self::hubLink('superadmin.people.merge.index', 'people.nav_merge', 'people.nav_merge_desc', 'bi-people', ['superadmin.people.*'], true),
            self::hubLink('admin.services.index', 'service.manage_title', 'pages.superadmin_services_desc', 'fas fa-church', ['admin.services.*'], true),
            self::hubLink('superadmin.courses', 'pages.manage_courses', 'pages.superadmin_courses_desc', 'bi-journal-bookmark-fill', ['superadmin.courses'], true),
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
            self::hubLink('superadmin.security', 'pages.superadmin_security_title', 'pages.superadmin_security_desc', 'bi-shield-lock', ['superadmin.security', 'superadmin.sessions.*', 'superadmin.impersonate'], true),
            self::hubLink('superadmin.audit.index', 'nav.audit_reports', 'pages.superadmin_audit_desc', 'bi-journal-text', ['superadmin.audit.*'], true),
            self::hubLink('superadmin.events.tests.index', 'nav.events_tests', 'pages.superadmin_events_tests_desc', 'bi-bug', ['superadmin.events.tests.*'], true),
            self::hubLink('superadmin.system-tests.index', 'nav.system_tests', 'pages.superadmin_system_tests_desc', 'bi-clipboard2-check', ['superadmin.system-tests.*'], true),
        ];

        $sharedLinks = [];
        if ($currentCourse) {
            $sharedLinks[] = [
                'url' => route('curriculum.show', $currentCourse->course_id),
                'label' => __('nav.curriculum').' — '.$currentCourse->localizedTitle(),
                'description' => __('pages.superadmin_course_curriculum_desc'),
                'icon' => 'bi-journal-bookmark',
                'active' => request()->routeIs('curriculum.show', 'curriculum.admin'),
                'superadmin_only' => false,
            ];
            $sharedLinks[] = [
                'url' => $hub->hubUrl($currentCourse, 'course'),
                'label' => __('rbac.hub_title').' — '.$currentCourse->localizedTitle(),
                'description' => __('pages.superadmin_course_roles_desc'),
                'icon' => 'bi-shield-check',
                'active' => request()->routeIs('roles.hub', 'courses.roles.*'),
                'superadmin_only' => false,
            ];
            $sharedLinks[] = [
                'url' => route('graduation.show', $currentCourse->course_id),
                'label' => __('pages.graduation_title').' — '.$currentCourse->localizedTitle(),
                'description' => __('pages.superadmin_course_graduation_desc'),
                'icon' => 'bi-mortarboard',
                'active' => request()->routeIs('graduation.show', 'graduation.export'),
                'superadmin_only' => false,
            ];
        }

        $sharedLinks[] = self::hubLink('hubs.academic', 'nav.academic', 'nav.academic_desc', 'bi-mortarboard', ['hubs.academic'], false);
        $sharedLinks[] = self::hubLink('hubs.service', 'nav.service', 'nav.service_desc', 'fas fa-church', ['hubs.service', 'services.select', 'services.roster', 'admin.services.*'], false);
        $sharedLinks[] = self::hubLink('hubs.system', 'nav.system_settings', 'nav.system_settings_desc', 'bi-gear', ['hubs.system'], false);
        $sharedLinks[] = self::hubLink('courses.select', 'course_context.switch_course', 'pages.superadmin_course_picker_desc', 'bi-grid', ['courses.select'], false);

        return [
            [
                'title' => __('pages.superadmin_section_exclusive'),
                'links' => $exclusiveLinks,
            ],
            [
                'title' => __('pages.superadmin_section_shared'),
                'links' => $sharedLinks,
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
        return $user instanceof User && ($user->is_superadmin ?? false);
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

        if (request()->routeIs('hubs.service', 'services.select', 'services.select.*', 'services.roster', 'services.apply', 'services.apply.store', 'services.application.status', 'admin.service-applications.*', 'admin.services.*', 'church.priests.*', 'church.confession.*', 'church.home-visits.*', 'church.finance.*')) {
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

    protected static function hubLink(string $routeName, string $labelKey, string $descKey, string $icon, array $patterns, bool $superadminOnly = false): array
    {
        $link = self::link($routeName, $labelKey, $icon, $patterns);
        $link['description'] = __($descKey);
        $link['superadmin_only'] = $superadminOnly;

        return $link;
    }

    protected static function link(string $routeName, string $labelKey, string $icon, array $patterns, ?string $permission = null, ?string $capability = null): array
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
            ];
        }

        return [
            'url' => route($routeName),
            'label' => __($labelKey),
            'icon' => $icon,
            'active' => request()->routeIs(...$patterns),
            'permission' => $permission,
            'capability' => $capability,
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
            $links[] = [
                'url' => $hub->hubUrl(),
                'label' => __('rbac.hub_title'),
                'icon' => 'bi-shield-check',
                'active' => request()->routeIs('roles.hub', 'user-course-roles.*', 'roles.*', 'courses.roles.*'),
            ];
            $links[] = self::link('admin.translations.index', 'nav.translations', 'bi-translate', ['admin.translations.*']);
            $links[] = self::link('admin.attendance-settings.edit', 'pages.attendance_settings_title', 'bi-sliders', ['admin.attendance-settings.*']);
            $links[] = self::link('admin.profile-photos.index', 'profile_photos.report_title', 'bi-person-badge', ['admin.profile-photos.*']);
            $links[] = self::link('admin.registration-applications.index', 'registration_review.queue_title', 'bi-clipboard-check', ['admin.registration-applications.*']);
            $links[] = self::link('admin.courses.application-forms.index', 'course_applications.builder_index_title', 'bi-ui-checks', ['admin.courses.application-forms.*', 'admin.courses.application-form.*']);
            $links[] = self::link('admin.course-applications.index', 'course_applications.queue_title', 'bi-journal-check', ['admin.course-applications.*']);
            $links[] = self::link('admin.graduation-settings.index', 'pages.graduation_configure_criteria', 'bi-award', ['admin.graduation-settings.*']);
        }

        return $links;
    }
}
