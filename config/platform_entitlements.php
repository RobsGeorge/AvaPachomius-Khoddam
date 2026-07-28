<?php

/*
|--------------------------------------------------------------------------
| Platform entitlement catalog (T9 — subscription feature matrix)
|--------------------------------------------------------------------------
|
| Code-defined stable keys enforced by EntitlementResolver. Boolean entries
| with maps_to_capability sync into church_capability via EntitlementSyncService.
| Superadmin plan builder reads PlatformFeature rows seeded from this file.
|
*/

return [

  // --- Capability modules (boolean → church_capability) ---
  'curriculum' => [
    'type' => 'boolean',
    'maps_to_capability' => 'curriculum',
    'label' => 'billing.features.curriculum',
    'sort_order' => 10,
  ],
  'attendance' => [
    'type' => 'boolean',
    'maps_to_capability' => 'attendance',
    'label' => 'billing.features.attendance',
    'sort_order' => 20,
  ],
  'assignments' => [
    'type' => 'boolean',
    'maps_to_capability' => 'assignments',
    'label' => 'billing.features.assignments',
    'sort_order' => 30,
  ],
  'exams' => [
    'type' => 'boolean',
    'maps_to_capability' => 'exams',
    'label' => 'billing.features.exams',
    'sort_order' => 40,
  ],
  'grades' => [
    'type' => 'boolean',
    'maps_to_capability' => 'grades',
    'label' => 'billing.features.grades',
    'sort_order' => 50,
  ],
  'assessments' => [
    'type' => 'boolean',
    'maps_to_capability' => 'assessments',
    'label' => 'billing.features.assessments',
    'sort_order' => 55,
  ],
  'events' => [
    'type' => 'boolean',
    'maps_to_capability' => 'events',
    'label' => 'billing.features.events',
    'sort_order' => 60,
  ],
  'live_quiz' => [
    'type' => 'boolean',
    'maps_to_capability' => 'live_quiz',
    'label' => 'billing.features.live_quiz',
    'sort_order' => 70,
  ],
  'feedback' => [
    'type' => 'boolean',
    'maps_to_capability' => 'feedback',
    'label' => 'billing.features.feedback',
    'sort_order' => 80,
  ],
  'announcements' => [
    'type' => 'boolean',
    'maps_to_capability' => 'announcements',
    'label' => 'billing.features.announcements',
    'sort_order' => 90,
  ],
  'reporting' => [
    'type' => 'boolean',
    'maps_to_capability' => 'reporting',
    'label' => 'billing.features.reporting',
    'sort_order' => 100,
  ],
  'church_management' => [
    'type' => 'boolean',
    'maps_to_capability' => 'church_management',
    'label' => 'billing.features.church_management',
    'sort_order' => 110,
  ],

  // --- Limits ---
  'max_active_users' => [
    'type' => 'limit',
    'maps_to_capability' => null,
    'label' => 'billing.features.max_active_users',
    'sort_order' => 200,
    'default' => 50,
  ],
  'storage_bytes' => [
    'type' => 'limit',
    'maps_to_capability' => null,
    'label' => 'billing.features.storage_bytes',
    'sort_order' => 210,
    'default' => 2_147_483_648, // 2 GB
  ],
  'max_courses' => [
    'type' => 'limit',
    'maps_to_capability' => null,
    'label' => 'billing.features.max_courses',
    'sort_order' => 220,
    'default' => null, // null = unlimited
  ],

  // --- Enums ---
  'mobile_app' => [
    'type' => 'enum',
    'maps_to_capability' => null,
    'label' => 'billing.features.mobile_app',
    'sort_order' => 300,
    'enum_options' => ['none', 'student', 'full'],
    'default' => 'none',
  ],

  // --- Infrastructure ---
  'custom_domain' => [
    'type' => 'boolean',
    'maps_to_capability' => null,
    'label' => 'billing.features.custom_domain',
    'sort_order' => 400,
    'default' => false,
  ],
  'api_access' => [
    'type' => 'boolean',
    'maps_to_capability' => null,
    'label' => 'billing.features.api_access',
    'sort_order' => 410,
    'default' => false,
  ],
  'white_label' => [
    'type' => 'boolean',
    'maps_to_capability' => null,
    'label' => 'billing.features.white_label',
    'sort_order' => 420,
    'default' => false,
  ],

];
