<?php

namespace App\Console\Commands;

use App\Services\QaCourseTestersService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Provision or wipe a multi-course QA testing matrix.
 * Refuses production unless --force (never use demo:seed on prod).
 */
class QaCourseTestersCommand extends Command
{
    protected $signature = 'qa:course-testers
                            {--course= : Single course_id (shorthand for --courses)}
                            {--courses= : Comma-separated course_ids (default: all eligible)}
                            {--admins=3 : Number of course-admin personas}
                            {--instructors=2 : Number of instructor personas}
                            {--students=12 : Number of student personas}
                            {--password= : Shared login password (auto-generated if omitted)}
                            {--domain= : Email domain (default: avapakhomios.qa)}
                            {--force : Allow running when APP_ENV=production}
                            {--wipe : Remove qa.matrix.* and legacy qa.course.* accounts}
                            {--dry-run : Print the plan without writing}';

    protected $description = 'Provision or wipe a multi-course QA admin/instructor/student matrix';

    public function handle(QaCourseTestersService $qa): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run on production without --force.');

            return self::FAILURE;
        }

        $domain = (string) ($this->option('domain') ?: QaCourseTestersService::DEFAULT_EMAIL_DOMAIN);
        if (($domainError = $this->validateDomain($domain)) !== null) {
            $this->error($domainError);

            return self::FAILURE;
        }

        if ($this->option('wipe')) {
            return $this->wipe($qa, $domain);
        }

        return $this->provision($qa, $domain);
    }

    private function provision(QaCourseTestersService $qa, string $domain): int
    {
        try {
            $courseIds = $this->parseCourseIds();
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $admins = max(1, (int) $this->option('admins'));
        $instructors = max(0, (int) $this->option('instructors'));
        $students = max(0, (int) $this->option('students'));
        $password = $this->option('password') ? (string) $this->option('password') : null;

        if ($password !== null && ($passwordError = $this->validatePassword($password)) !== null) {
            $this->error($passwordError);

            return self::FAILURE;
        }

        try {
            $courses = $qa->resolveCourses($courseIds);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $plan = $qa->buildMatrixPlan($courses, $admins, $instructors, $students);

        $this->info('Courses ('.$courses->count().'):');
        foreach ($courses as $course) {
            $this->line("  #{$course->course_id} {$course->title}");
        }

        $this->newLine();
        $this->info('Matrix plan ('.$admins.' admins / '.$instructors.' instructors / '.$students.' students):');
        foreach ($plan as $row) {
            $roles = collect($row['course_roles'])
                ->map(fn (array $r) => '#'.$r['course_id'].':'.$r['slug'])
                ->implode(', ');
            $svc = $row['service_admin_ids'] !== []
                ? ' svc-admin=['.implode(',', $row['service_admin_ids']).']'
                : '';
            $this->line("  {$row['email_local']}@{$domain} → {$roles}{$svc}");
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        try {
            $result = $qa->provision(
                password: $password,
                domain: $domain,
                courseIds: $courses->pluck('course_id')->map(fn ($id) => (int) $id)->all(),
                admins: $admins,
                instructors: $instructors,
                students: $students,
            );
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Provisioned '.count($result['accounts']).' QA accounts:');
        foreach ($result['accounts'] as $row) {
            $verb = $row['created'] ? 'created' : 'updated';
            $roles = collect($row['course_roles'])
                ->map(fn (array $r) => '#'.$r['course_id'].':'.$r['slug'])
                ->implode(', ');
            $this->line("  [{$verb}] {$row['email']} ({$roles})");
        }
        $this->newLine();
        $this->warn('Shared password: '.$result['password']);
        $this->line('Credentials file: storage/app/'.QaCourseTestersService::CREDENTIALS_RELATIVE_PATH);
        $this->line('Login: '.(string) config('app.url').'/login');

        return self::SUCCESS;
    }

    private function wipe(QaCourseTestersService $qa, string $domain): int
    {
        $existing = $qa->emails($domain);
        if ($existing === []) {
            $this->warn('No qa.matrix.* / qa.course.* users found for '.$domain);
        } else {
            $this->warn('Will delete '.count($existing).' user(s):');
            foreach ($existing as $email) {
                $this->line('  '.$email);
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        $result = $qa->wipe($domain);
        $this->info("Deleted {$result['deleted_users']} QA user(s).");

        return self::SUCCESS;
    }

    /** @return list<int>|null */
    private function parseCourseIds(): ?array
    {
        $coursesOpt = $this->option('courses');
        $courseOpt = $this->option('course');

        $raw = null;
        if (is_string($coursesOpt) && $coursesOpt !== '') {
            $raw = $coursesOpt;
        } elseif (is_string($courseOpt) && $courseOpt !== '') {
            $raw = $courseOpt;
        }

        if ($raw === null) {
            return null;
        }

        $ids = [];
        $invalid = [];
        foreach (preg_split('/\s*,\s*/', $raw) as $part) {
            if ($part === '') {
                continue;
            }
            // Reject "0", negatives, and non-numeric tokens that cast to 0.
            if (! preg_match('/^[1-9]\d*$/', $part)) {
                $invalid[] = $part;
                continue;
            }
            $ids[] = (int) $part;
        }

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'course' => 'Invalid course_id(s): '.implode(', ', $invalid).'. Use positive integers.',
            ]);
        }

        return $ids === [] ? null : array_values(array_unique($ids));
    }

    private function validateDomain(string $domain): ?string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return 'Email domain cannot be empty.';
        }

        if (str_contains($domain, '@') || str_contains($domain, ' ')) {
            return 'Email domain must not include @ or spaces.';
        }

        // Allow reserved TLDs used in tests (.test / .qa) and normal DNS labels.
        if (! preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)) {
            return 'Email domain is not a valid hostname (e.g. avapakhomios.qa).';
        }

        return null;
    }

    private function validatePassword(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters when provided.';
        }

        return null;
    }
}
