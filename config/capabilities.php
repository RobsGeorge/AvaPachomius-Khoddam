<?php

/*
|--------------------------------------------------------------------------
| Capability catalog (T2 — per-church feature switches)
|--------------------------------------------------------------------------
|
| Code-defined, stable catalog of the toggleable feature areas. Each church
| enables a subset (church_capability table); a disabled capability 404s its
| routes and hides its nav — for that church only. `config` holds catalog
| defaults, overridable per church. `permissions` bounds what P3 roles may
| grant within the feature (populated in T3).
|
| Ceiling model: capability (T2) → permission (T3) → role grant (T3).
|
*/

return [

    'curriculum' => [
        'label' => 'capabilities.curriculum',
        'permissions' => [],
        'config' => ['modules' => true, 'recurring_years' => false],
    ],

    'attendance' => [
        'label' => 'capabilities.attendance',
        'permissions' => [],
        'config' => ['mode' => 'strict', 'min_percentage' => 75, 'penalty' => true],
    ],

    'assignments' => [
        'label' => 'capabilities.assignments',
        'permissions' => [],
        'config' => [],
    ],

    'exams' => [
        'label' => 'capabilities.exams',
        'permissions' => [],
        'config' => [],
    ],

    'grades' => [
        'label' => 'capabilities.grades',
        'permissions' => [],
        'config' => [],
    ],

    'assessments' => [
        'label' => 'capabilities.assessments',
        'permissions' => [],
        'config' => [],
    ],

    'events' => [
        'label' => 'capabilities.events',
        'permissions' => [],
        'config' => [],
    ],

    'live_quiz' => [
        'label' => 'capabilities.live_quiz',
        'permissions' => [],
        'config' => [],
    ],

    'feedback' => [
        'label' => 'capabilities.feedback',
        'permissions' => [],
        'config' => [],
    ],

    'announcements' => [
        'label' => 'capabilities.announcements',
        'permissions' => [],
        'config' => [],
    ],

    'reporting' => [
        'label' => 'capabilities.reporting',
        'permissions' => [],
        'config' => [],
    ],

];
