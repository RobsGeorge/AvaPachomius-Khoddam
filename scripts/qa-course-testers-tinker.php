<?php
/**
 * PASTE into production tinker for the full multi-course QA matrix
 * (works after this branch is on the server, OR use artisan qa:course-testers).
 *
 *   cd /var/www/avapakhomios && php8.2 artisan tinker
 *
 * Prefer:
 *   php8.2 artisan qa:course-testers --force --admins=3 --instructors=2 --students=12
 */

$courseIds = null; // e.g. [1, 2, 3] or null = all eligible courses
$plain = 'QaTesters-ChangeMe1!';
$domain = 'avapakhomios.qa';
$admins = 3;
$instructors = 2;
$students = 12;

$qa = app(\App\Services\QaCourseTestersService::class);
$result = $qa->provision(
    password: $plain,
    domain: $domain,
    courseIds: $courseIds,
    admins: $admins,
    instructors: $instructors,
    students: $students,
);

foreach ($result['accounts'] as $row) {
    $roles = collect($row['course_roles'])->map(fn ($r) => '#'.$r['course_id'].':'.$r['slug'])->implode(',');
    echo ($row['created'] ? 'created' : 'updated')." {$row['email']} → {$roles}\n";
}
echo "Password: {$result['password']}\n";
echo 'Credentials: storage/app/'.\App\Services\QaCourseTestersService::CREDENTIALS_RELATIVE_PATH."\n";
