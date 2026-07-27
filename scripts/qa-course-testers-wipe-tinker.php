<?php
/**
 * PASTE into production tinker to wipe QA matrix (+ legacy qa.course.*) accounts.
 *
 *   php8.2 artisan tinker
 *
 * Or: php8.2 artisan qa:course-testers --wipe --force
 */

$domain = 'avapakhomios.qa';
$result = app(\App\Services\QaCourseTestersService::class)->wipe($domain);
echo "Deleted {$result['deleted_users']} user(s)\n";
foreach ($result['emails'] as $email) {
    echo "  {$email}\n";
}
