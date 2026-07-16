<?php

/*
|--------------------------------------------------------------------------
| Capability catalog (T2 — per-church feature switches; T3 wires permissions)
|--------------------------------------------------------------------------
|
| Code-defined, stable catalog of the toggleable feature areas. Each church
| enables a subset (church_capability table); a disabled capability 404s its
| routes and hides its nav — for that church only. `config` holds catalog
| defaults, overridable per church. `permissions` bounds what roles may
| grant within the feature (ceiling model: capability → permission → grant).
|
*/

return [

    'curriculum' => [
        'label' => 'capabilities.curriculum',
        'permissions' => [
            'course.access', 'course.view',
            'curriculum.view', 'curriculum.manage',
            'session.notify',
        ],
        'config' => ['modules' => true, 'recurring_years' => false],
    ],

    'attendance' => [
        'label' => 'capabilities.attendance',
        'permissions' => [
            'attendance.record', 'attendance.view_all', 'attendance.view_own',
            'attendance.configure', 'attendance.edit', 'attendance.report',
        ],
        'config' => ['mode' => 'strict', 'min_percentage' => 75, 'penalty' => true],
    ],

    'assignments' => [
        'label' => 'capabilities.assignments',
        'permissions' => [
            'assignment.view', 'assignment.manage', 'assignment.submit', 'assignment.grade',
        ],
        'config' => [],
    ],

    'exams' => [
        'label' => 'capabilities.exams',
        'permissions' => [
            'exam.view', 'exam.author', 'exam.schedule', 'exam.grade', 'exam.take', 'exam.proctor',
        ],
        'config' => [],
    ],

    'grades' => [
        'label' => 'capabilities.grades',
        'permissions' => [
            'grade.view', 'grade.manage',
            'graduation.view', 'graduation.configure', 'graduation.settings',
            'certificate.download', 'certificate.manage', 'course.close',
        ],
        'config' => [],
    ],

    'assessments' => [
        'label' => 'capabilities.assessments',
        'permissions' => [],
        'config' => [],
    ],

    'events' => [
        'label' => 'capabilities.events',
        'permissions' => [
            'events.view', 'events.reserve', 'events.check_in', 'events.admin',
        ],
        'config' => [],
    ],

    'live_quiz' => [
        'label' => 'capabilities.live_quiz',
        'permissions' => [
            'live_quiz.play', 'live_quiz.host', 'live_quiz.manage',
        ],
        'config' => [],
    ],

    'feedback' => [
        'label' => 'capabilities.feedback',
        'permissions' => [
            'feedback.view', 'feedback.manage', 'feedback.report',
        ],
        'config' => [],
    ],

    'announcements' => [
        'label' => 'capabilities.announcements',
        'permissions' => [
            'announcement.view', 'announcement.manage', 'announcement.publish',
        ],
        'config' => [],
    ],

    'reporting' => [
        'label' => 'capabilities.reporting',
        'permissions' => [
            'communications.report', 'roster.view', 'roster.announce',
        ],
        'config' => [],
    ],

    'church_management' => [
        'label' => 'capabilities.church_management',
        'permissions' => [
            'church.configure', 'church.members.manage', 'church.role.manage',
        ],
        'config' => [],
    ],

];
