<?php

namespace App\Support;

use App\Models\Course;
use App\Models\User;
use App\Services\CoursePermissionResolver;
use App\Services\RolesHubService;

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
            $links[] = self::link('curriculum.index', 'nav.curriculum', 'bi-journal-bookmark', ['curriculum.*'], 'curriculum.view');
        }

        if (self::canAnyCourse($user, $resolver, ['curriculum.manage'])) {
            $links[] = self::link('sessions.index', 'nav.sessions', 'bi-calendar3', ['sessions.*'], 'curriculum.manage');
            $links[] = self::link('modules.index', 'nav.modules', 'bi-collection', ['modules.*'], 'curriculum.manage');
        }

        if (self::canAnyCourse($user, $resolver, ['assignment.manage'])) {
            $links[] = self::link('assignments.dashboard', 'dashboard.manage_assignments', 'bi-journal-text', [
                'assignments.dashboard', 'assignments.create', 'assignments.edit', 'assignments.status',
            ], 'assignment.manage');
        }

        if (self::canAnyCourse($user, $resolver, ['assignment.view', 'assignment.manage'])) {
            $links[] = self::link('assignments.index', 'dashboard.view_assignments', 'bi-journal-check', [
                'assignments.index', 'assignments.show',
            ], 'assignment.view');
        }

        if (self::canAnyCourse($user, $resolver, ['exam.author', 'exam.grade'])) {
            $links[] = self::link('exams.dashboard', 'dashboard.manage_exams', 'bi-patch-check', [
                'exams.dashboard', 'exams.builder', 'exams.grades', 'exams.admin-dashboard',
            ], 'exam.author');
        }

        if (self::canAnyCourse($user, $resolver, ['exam.view', 'exam.take'])) {
            $links[] = self::link('exams.index', 'dashboard.view_exams', 'bi-calendar2-check', [
                'exams.index', 'exams.attempt.*',
            ], 'exam.view');
        }

        if (self::canAnyCourse($user, $resolver, ['attendance.view_all'])) {
            $links[] = self::link('attendance.all', 'nav.attendance', 'bi-calendar-check', [
                'attendance.all', 'attendance.user', 'attendance.by-date', 'attendance.user-report',
            ], 'attendance.view_all');
            $links[] = self::link('attendance.report', 'dashboard.attendance_report', 'bi-graph-up', ['attendance.report'], 'attendance.report');
        }

        if (self::canAnyCourse($user, $resolver, ['roster.view'])) {
            $links[] = self::link('students.roster', 'students.roster_title', 'bi-person-lines-fill', ['students.roster', 'students.roster.announce'], 'roster.view');
        }

        if (self::canAnyCourse($user, $resolver, ['announcement.manage'])) {
            $links[] = self::link('announcements.manage.index', 'announcements.manage_title', 'bi-megaphone', ['announcements.manage.*'], 'announcement.manage');
        }

        if (self::canAnyCourse($user, $resolver, ['graduation.view', 'course.close'])) {
            $links[] = self::link('graduation.index', 'pages.graduation_title', 'bi-mortarboard', ['graduation.*'], 'graduation.view');
        }

        if (self::canAnyCourse($user, $resolver, ['course.view']) && ! self::canAnyCourse($user, $resolver, ['curriculum.manage'])) {
            $links[] = self::link('available-courses.index', 'course_applications.available_courses_title', 'bi-mortarboard', [
                'available-courses.index', 'courses.apply', 'courses.apply.store',
                'courses.application.status', 'courses.application.edit', 'courses.application.update',
            ], 'course.view');
        }

        if (self::canAnyCourse($user, $resolver, ['attendance.view_own']) && ! self::canAnyCourse($user, $resolver, ['attendance.view_all'])) {
            $links[] = self::link('attendance.my', 'nav.my_attendance', 'bi-calendar-check', ['attendance.my'], 'attendance.view_own');
        }

        if (self::canAnyCourse($user, $resolver, ['roster.view']) && ! self::canAnyCourse($user, $resolver, ['roster.announce'])) {
            $links[] = self::link('students.birthdays', 'students.birthdays_title', 'bi-cake2', ['students.birthdays'], 'roster.view');
        }

        if (self::canAnyCourse($user, $resolver, ['announcement.view']) && ! self::canAnyCourse($user, $resolver, ['announcement.manage'])) {
            $links[] = self::link('announcements.index', 'announcements.title', 'bi-megaphone', [
                'announcements.index', 'announcements.show', 'announcements.dismiss-banner',
            ], 'announcement.view');
        }

        if (self::canAnyCourse($user, $resolver, ['feedback.view', 'feedback.manage'])) {
            $links[] = self::link('feedback.index', 'dashboard.feedback', 'bi-chat-square-text', ['feedback.*'], 'feedback.view');
        }

        if (self::canAnyCourse($user, $resolver, ['live_quiz.play', 'live_quiz.manage'])) {
            $links[] = self::link('live-quiz.index', 'dashboard.live_quiz', 'bi-lightning-charge', ['live-quiz.*'], 'live_quiz.play');
        }

        if ($user->canInSystem('events.view') || self::canAnyCourse($user, $resolver, ['events.view'])) {
            $links[] = self::link('events.index', 'nav.events', 'bi-calendar-event', ['events.index', 'events.show'], 'events.view');
            $links[] = self::link('events.my-reservations', 'events.my_reservations', 'bi-ticket-perforated', ['events.my-reservations'], 'events.reserve');
        }

        if ($user->isEventAdmin()) {
            $links[] = self::link('events.admin.index', 'nav.events_admin', 'bi-gear', [
                'events.admin.*', 'events.check-in.verify',
            ], 'events.admin');
        }

        return $links;
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

    protected static function link(string $routeName, string $labelKey, string $icon, array $patterns, ?string $permission = null): array
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
            ];
        }

        if ($course && $routeName === 'graduation.index') {
            return [
                'url' => route('graduation.show', $course->course_id),
                'label' => __($labelKey),
                'icon' => $icon,
                'active' => request()->routeIs('graduation.show', 'graduation.export', 'graduation.*'),
                'permission' => $permission,
            ];
        }

        return [
            'url' => route($routeName),
            'label' => __($labelKey),
            'icon' => $icon,
            'active' => request()->routeIs(...$patterns),
            'permission' => $permission,
        ];
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
        if ($user->is_superadmin ?? false) {
            return true;
        }

        foreach ($permissions as $perm) {
            if ($user->canInSystem($perm)) {
                return true;
            }
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
