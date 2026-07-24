<?php

/*
|--------------------------------------------------------------------------
| Demo / staging seed data
|--------------------------------------------------------------------------
|
| Controls the `demo:seed` / `demo:wipe` commands that populate a staging or
| local environment with representative dummy data (churches, services,
| priests, courses, students, admins, roles, …) that can be logged into and
| removed cleanly. NEVER meant for production.
|
| Turn it on only where you want it, by setting DEMO_SEED_ENABLED=true in that
| environment's .env (then `php artisan config:clear`). The commands refuse to
| run when this is false or when APP_ENV=production.
|
*/

return [
    // Master switch for the demo:seed / demo:wipe commands.
    'enabled' => (bool) env('DEMO_SEED_ENABLED', false),

    // Markers that make every piece of demo data identifiable (and safely removable).
    'church_slug_prefix' => 'demo-',
    'email_domain' => 'demo.khedma.test',

    // Shared password for every seeded account, so you can log in immediately.
    // Override per-environment if you like.
    'password' => env('DEMO_SEED_PASSWORD', 'Demo1234!'),
];
