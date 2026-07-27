<?php

/**
 * Server-side one-shot for the QA multi-course matrix.
 *
 *   cd /var/www/avapakhomios
 *   php8.2 scripts/qa-course-testers.php --force
 *   php8.2 scripts/qa-course-testers.php --force --courses=1,2,3 --students=12 --admins=3
 *   php8.2 scripts/qa-course-testers.php --force --dry-run
 *   php8.2 scripts/qa-course-testers.php --force --wipe
 *
 * Prefer after deploy:
 *   php8.2 artisan qa:course-testers --force --admins=3 --instructors=2 --students=12
 */

use App\Services\QaCourseTestersService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$opts = getopt('', [
    'course::',
    'courses::',
    'admins::',
    'instructors::',
    'students::',
    'password::',
    'domain::',
    'wipe',
    'dry-run',
    'force',
    'help',
]);

if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php8.2 scripts/qa-course-testers.php --force [--courses=1,2] [--admins=3] [--instructors=2] [--students=12] [--password=...] [--domain=...] [--wipe] [--dry-run]\n");
    exit(0);
}

if ($app->environment('production') && ! isset($opts['force'])) {
    fwrite(STDERR, "Refusing production without --force\n");
    exit(1);
}

/** @var QaCourseTestersService $qa */
$qa = $app->make(QaCourseTestersService::class);
$domain = $opts['domain'] ?? QaCourseTestersService::DEFAULT_EMAIL_DOMAIN;

$parseIds = static function (?string $raw): ?array {
    if ($raw === null || $raw === '') {
        return null;
    }
    $ids = [];
    foreach (preg_split('/\s*,\s*/', $raw) as $part) {
        if ($part !== '') {
            $ids[] = (int) $part;
        }
    }

    return $ids === [] ? null : $ids;
};

try {
    if (isset($opts['wipe'])) {
        if (isset($opts['dry-run'])) {
            $emails = $qa->emails($domain);
            fwrite(STDOUT, 'Would wipe '.count($emails)." user(s)\n");
            foreach ($emails as $email) {
                fwrite(STDOUT, "  {$email}\n");
            }
            exit(0);
        }
        $result = $qa->wipe($domain);
        fwrite(STDOUT, "Deleted {$result['deleted_users']} user(s).\n");
        exit(0);
    }

    $courseIds = $parseIds($opts['courses'] ?? null);
    if ($courseIds === null && isset($opts['course'])) {
        $courseIds = [(int) $opts['course']];
    }

    $admins = isset($opts['admins']) ? (int) $opts['admins'] : QaCourseTestersService::DEFAULT_ADMINS;
    $instructors = isset($opts['instructors']) ? (int) $opts['instructors'] : QaCourseTestersService::DEFAULT_INSTRUCTORS;
    $students = isset($opts['students']) ? (int) $opts['students'] : QaCourseTestersService::DEFAULT_STUDENTS;

    $courses = $qa->resolveCourses($courseIds);
    $plan = $qa->buildMatrixPlan($courses, $admins, $instructors, $students);

    fwrite(STDOUT, 'Courses: '.$courses->pluck('course_id')->implode(',').PHP_EOL);
    fwrite(STDOUT, 'Plan rows: '.count($plan).PHP_EOL);
    foreach ($plan as $row) {
        $roles = collect($row['course_roles'])->map(fn ($r) => '#'.$r['course_id'].':'.$r['slug'])->implode(',');
        fwrite(STDOUT, "  {$row['email_local']}@{$domain} → {$roles}\n");
    }

    if (isset($opts['dry-run'])) {
        fwrite(STDOUT, "Dry run only.\n");
        exit(0);
    }

    $result = $qa->provision(
        password: $opts['password'] ?? null,
        domain: $domain,
        courseIds: $courses->pluck('course_id')->map(fn ($id) => (int) $id)->all(),
        admins: $admins,
        instructors: $instructors,
        students: $students,
    );

    foreach ($result['accounts'] as $row) {
        $verb = $row['created'] ? 'created' : 'updated';
        fwrite(STDOUT, "[{$verb}] {$row['email']}\n");
    }
    fwrite(STDOUT, 'Password: '.$result['password'].PHP_EOL);
    fwrite(STDOUT, 'Credentials: storage/app/'.QaCourseTestersService::CREDENTIALS_RELATIVE_PATH.PHP_EOL);
} catch (ValidationException $e) {
    fwrite(STDERR, collect($e->errors())->flatten()->implode(' ').PHP_EOL);
    exit(1);
}
