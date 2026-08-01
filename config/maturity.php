<?php

/**
 * Maturity ladder / age-policy defaults.
 *
 * Services MUST read thresholds via AgePolicyResolver (org policy row, then these
 * defaults). Do not scatter literal ages in application code.
 *
 * Default majority (18) and digital-consent (13) appear ONLY here as platform
 * fallbacks when no age_policies row exists for the church's organization chain.
 */
return [
    'defaults' => [
        'digital_consent_age' => (int) env('MATURITY_DEFAULT_DIGITAL_CONSENT_AGE', 13),
        'age_of_majority' => (int) env('MATURITY_DEFAULT_AGE_OF_MAJORITY', 18),
    ],

    'guardian_visibility' => [
        'full',
        'restricted',
    ],

    'consent_scopes' => [
        'guardian_custody',
        'rung2_credential',
        'self_emancipation',
    ],
];
