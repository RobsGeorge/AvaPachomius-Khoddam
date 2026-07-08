<?php

namespace App\Support;

use App\Models\User;

class NavigationHub
{
    public static function academicLinks(User $user): array
    {
        $links = [
            self::link('curriculum.index', 'nav.curriculum', 'bi-journal-bookmark', ['curriculum.*']),
            self::link('sessions.index', 'nav.sessions', 'bi-calendar3', ['sessions.*']),
        ];

        if ($user->isInstructorOrAdmin()) {
            $links[] = self::link('assignments.dashboard', 'dashboard.manage_assignments', 'bi-journal-text', [
                'assignments.dashboard', 'assignments.create', 'assignments.edit', 'assignments.status',
            ]);
            $links[] = self::link('assignments.index', 'dashboard.view_assignments', 'bi-journal-check', [
                'assignments.index', 'assignments.show',
            ]);
            $links[] = self::link('exams.dashboard', 'dashboard.manage_exams', 'bi-patch-check', [
                'exams.dashboard', 'exams.builder', 'exams.grades', 'exams.admin-dashboard',
            ]);
            $links[] = self::link('exams.index', 'dashboard.view_exams', 'bi-calendar2-check', [
                'exams.index', 'exams.attempt.*',
            ]);
            $links[] = self::link('attendance.all', 'nav.attendance', 'bi-calendar-check', [
                'attendance.all', 'attendance.user', 'attendance.by-date', 'attendance.user-report',
            ]);
            $links[] = self::link('attendance.report', 'dashboard.attendance_report', 'bi-graph-up', ['attendance.report']);
            $links[] = self::link('students.roster', 'students.roster_title', 'bi-person-lines-fill', ['students.roster', 'students.roster.announce']);
            $links[] = self::link('graduation.index', 'pages.graduation_title', 'bi-mortarboard', ['graduation.*']);
            $links[] = self::link('modules.index', 'nav.modules', 'bi-collection', ['modules.*']);
        } else {
            $links[] = self::link('assignments.index', 'dashboard.view_assignments', 'bi-journal-text', ['assignments.*']);
            $links[] = self::link('exams.index', 'dashboard.view_exams', 'bi-patch-check', ['exams.index', 'exams.attempt.*']);
            $links[] = self::link('attendance.my', 'nav.my_attendance', 'bi-calendar-check', ['attendance.my']);
            $links[] = self::link('students.birthdays', 'students.birthdays_title', 'bi-cake2', ['students.birthdays']);
        }

        $links[] = self::link('feedback.index', 'dashboard.feedback', 'bi-chat-square-text', ['feedback.*']);
        $links[] = self::link('live-quiz.index', 'dashboard.live_quiz', 'bi-lightning-charge', ['live-quiz.*']);
        $links[] = self::link('events.index', 'nav.events', 'bi-calendar-event', ['events.index', 'events.show']);
        $links[] = self::link('events.my-reservations', 'events.my_reservations', 'bi-ticket-perforated', ['events.my-reservations']);

        if ($user->isEventAdmin()) {
            $links[] = self::link('events.admin.index', 'nav.events_admin', 'bi-gear', [
                'events.admin.*', 'events.check-in.verify',
            ]);
        }

        return $links;
    }

    public static function systemLinks(User $user): array
    {
        $links = [];

        if ($user->isAdmin()) {
            $links[] = self::link('user-course-roles.index', 'nav.roles', 'bi-people', ['user-course-roles.*', 'roles.*']);
            $links[] = self::link('admin.translations.index', 'nav.translations', 'bi-translate', ['admin.translations.*']);
            $links[] = self::link('admin.attendance-settings.edit', 'pages.attendance_settings_title', 'bi-sliders', ['admin.attendance-settings.*']);
        }

        if ($user->is_superadmin || $user->isAdmin()) {
            $links[] = self::link('admin.graduation-settings.index', 'pages.graduation_configure_criteria', 'bi-award', ['admin.graduation-settings.*']);
        }

        if ($user->is_superadmin) {
            $links[] = self::link('superadmin.index', 'nav.superadmin', 'bi-shield-lock-fill', ['superadmin.index']);
            $links[] = self::link('superadmin.audit.index', 'nav.audit_reports', 'bi-journal-text', ['superadmin.audit.*']);
            $links[] = self::link('superadmin.events.tests.index', 'nav.events_tests', 'bi-bug', ['superadmin.events.tests.*']);
        }

        return $links;
    }

    public static function hasSystem(User $user): bool
    {
        return count(self::systemLinks($user)) > 0;
    }

    public static function isAcademicActive(User $user): bool
    {
        if (request()->routeIs('hubs.academic')) {
            return true;
        }

        return self::anyActive(self::academicLinks($user));
    }

    public static function isSystemActive(User $user): bool
    {
        if (request()->routeIs('hubs.system')) {
            return true;
        }

        return self::anyActive(self::systemLinks($user));
    }

    protected static function link(string $routeName, string $labelKey, string $icon, array $patterns): array
    {
        return [
            'url' => route($routeName),
            'label' => __($labelKey),
            'icon' => $icon,
            'active' => request()->routeIs(...$patterns),
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
}
