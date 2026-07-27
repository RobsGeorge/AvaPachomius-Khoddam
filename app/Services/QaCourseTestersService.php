<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserServiceRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Provision / wipe a QA testing matrix across courses (and optional service-admin).
 *
 * Email pattern: qa.matrix.{persona}{n}@{domain}
 * Also wipes legacy qa.course.* accounts from the earlier single-course trio.
 */
class QaCourseTestersService
{
    public const DEFAULT_EMAIL_DOMAIN = 'avapakhomios.qa';

    public const CREDENTIALS_RELATIVE_PATH = 'qa-course-testers-credentials.md';

    public const DEFAULT_ADMINS = 3;

    public const DEFAULT_INSTRUCTORS = 2;

    public const DEFAULT_STUDENTS = 12;

    /** Reserved identity block — national_id 2990101999xxxx / mobile 0109999xxxx */
    private const IDENTITY_BASE = 1000;

    public function __construct(
        private ServiceRoleAssignmentService $serviceAssigner,
        private CourseRoleAssignmentService $courseAssigner,
        private CoursePermissionResolver $resolver,
    ) {}

    public function emailForPersona(string $persona, int $index, string $domain = self::DEFAULT_EMAIL_DOMAIN): string
    {
        return 'qa.matrix.'.$persona.$index.'@'.$domain;
    }

    /**
     * @deprecated Use emailForPersona(); kept for older call sites / simple mode.
     */
    public function emailFor(string $slug, string $domain = self::DEFAULT_EMAIL_DOMAIN): string
    {
        return $this->emailForPersona($slug, 1, $domain);
    }

    /**
     * @param  list<int>|null  $courseIds
     * @return Collection<int, Course>
     */
    public function resolveCourses(?array $courseIds = null): Collection
    {
        $query = Course::query()->withoutGlobalScopes()->orderBy('course_id');

        if ($courseIds !== null && $courseIds !== []) {
            $courses = $query->whereIn('course_id', $courseIds)->get();
            $missing = array_diff($courseIds, $courses->pluck('course_id')->all());
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'course' => 'Unknown course_id(s): '.implode(', ', $missing),
                ]);
            }
        } else {
            $courses = $query->get();
        }

        $eligible = $courses->filter(
            fn (Course $c) => $this->courseHasRequiredRoles((int) $c->course_id)
        )->values();

        if ($eligible->isEmpty()) {
            throw ValidationException::withMessages([
                'course' => 'No course found with admin / instructor / student roles. Pass --courses=ID,ID.',
            ]);
        }

        return $eligible;
    }

    /** @deprecated Prefer resolveCourses() */
    public function resolveCourse(?int $courseId): Course
    {
        return $this->resolveCourses($courseId !== null ? [$courseId] : null)->first();
    }

    public function courseHasRequiredRoles(int $courseId): bool
    {
        foreach (['admin', 'instructor', 'student'] as $slug) {
            if ($this->roleIdFor($courseId, $slug) === null) {
                return false;
            }
        }

        return true;
    }

    public function roleIdFor(int $courseId, string $slug): ?int
    {
        $exact = Role::query()
            ->where('course_id', $courseId)
            ->where('slug', $slug)
            ->value('role_id');

        if ($exact) {
            return (int) $exact;
        }

        $prefixed = Role::query()
            ->where('course_id', $courseId)
            ->where('slug', 'like', $slug.'-%')
            ->where('is_template', false)
            ->orderBy('role_id')
            ->value('role_id');

        return $prefixed ? (int) $prefixed : null;
    }

    /**
     * Build the assignment plan (no DB writes).
     *
     * @param  Collection<int, Course>  $courses
     * @return list<array{
     *   persona: string,
     *   index: int,
     *   email_local: string,
     *   second_name: string,
     *   course_roles: list<array{course_id: int, slug: string}>,
     *   service_admin_ids: list<int>
     * }>
     */
    public function buildMatrixPlan(
        Collection $courses,
        int $admins = self::DEFAULT_ADMINS,
        int $instructors = self::DEFAULT_INSTRUCTORS,
        int $students = self::DEFAULT_STUDENTS,
    ): array {
        $admins = max(1, $admins);
        $instructors = max(0, $instructors);
        $students = max(0, $students);
        $n = $courses->count();
        $ids = $courses->pluck('course_id')->map(fn ($id) => (int) $id)->values()->all();
        $plan = [];

        for ($i = 1; $i <= $admins; $i++) {
            $courseRoles = [];
            $serviceAdminIds = [];

            if ($i === 1) {
                // Global course admin + service-admin on every parent service.
                foreach ($ids as $cid) {
                    $courseRoles[] = ['course_id' => $cid, 'slug' => 'admin'];
                }
                $serviceAdminIds = $this->uniqueServiceIds($courses);
            } elseif ($i === 2) {
                // Multi-course admin on the first half (at least one).
                $half = max(1, (int) ceil($n / 2));
                foreach (array_slice($ids, 0, $half) as $cid) {
                    $courseRoles[] = ['course_id' => $cid, 'slug' => 'admin'];
                }
                if ($n >= 2) {
                    // Also enrolled as student on the last course (cross-persona).
                    $last = $ids[$n - 1];
                    if (! in_array($last, array_column($courseRoles, 'course_id'), true)) {
                        $courseRoles[] = ['course_id' => $last, 'slug' => 'student'];
                    }
                }
                $serviceAdminIds = array_slice($this->uniqueServiceIds($courses), 0, 1);
            } else {
                // Remaining admins: second half of courses; round-robin extras.
                $start = (int) floor($n / 2);
                $slice = array_slice($ids, $start) ?: [$ids[($i - 1) % $n]];
                foreach ($slice as $cid) {
                    $courseRoles[] = ['course_id' => $cid, 'slug' => 'admin'];
                }
            }

            $plan[] = [
                'persona' => 'admin',
                'index' => $i,
                'email_local' => 'qa.matrix.admin'.$i,
                'second_name' => 'Admin'.$i,
                'course_roles' => $courseRoles,
                'service_admin_ids' => $serviceAdminIds,
            ];
        }

        for ($i = 1; $i <= $instructors; $i++) {
            $primary = $ids[($i - 1) % $n];
            $courseRoles = [['course_id' => $primary, 'slug' => 'instructor']];
            if ($n >= 2 && $i === 1) {
                $second = $ids[1 % $n];
                if ($second !== $primary) {
                    $courseRoles[] = ['course_id' => $second, 'slug' => 'instructor'];
                }
            }

            $plan[] = [
                'persona' => 'instructor',
                'index' => $i,
                'email_local' => 'qa.matrix.instructor'.$i,
                'second_name' => 'Instructor'.$i,
                'course_roles' => $courseRoles,
                'service_admin_ids' => [],
            ];
        }

        for ($i = 1; $i <= $students; $i++) {
            $primary = $ids[($i - 1) % $n];
            $courseRoles = [['course_id' => $primary, 'slug' => 'student']];
            // Every 3rd student dual-enrolls in another course when possible.
            if ($n >= 2 && $i % 3 === 0) {
                $second = $ids[$i % $n];
                if ($second !== $primary) {
                    $courseRoles[] = ['course_id' => $second, 'slug' => 'student'];
                }
            }

            $plan[] = [
                'persona' => 'student',
                'index' => $i,
                'email_local' => 'qa.matrix.student'.$i,
                'second_name' => 'Student'.$i,
                'course_roles' => $courseRoles,
                'service_admin_ids' => [],
            ];
        }

        return $plan;
    }

    /**
     * @param  list<int>|null  $courseIds
     * @return array{
     *   courses: Collection<int, Course>,
     *   password: string,
     *   accounts: list<array{
     *     persona: string,
     *     index: int,
     *     email: string,
     *     user_id: int,
     *     created: bool,
     *     course_roles: list<array{course_id: int, slug: string, role_id: int}>,
     *     service_admin_ids: list<int>
     *   }>
     * }
     */
    public function provision(
        ?int $courseId = null,
        ?string $password = null,
        string $domain = self::DEFAULT_EMAIL_DOMAIN,
        bool $writeCredentials = true,
        ?array $courseIds = null,
        int $admins = self::DEFAULT_ADMINS,
        int $instructors = self::DEFAULT_INSTRUCTORS,
        int $students = self::DEFAULT_STUDENTS,
    ): array {
        if ($courseIds === null && $courseId !== null) {
            $courseIds = [$courseId];
        }

        $courses = $this->resolveCourses($courseIds);
        $plan = $this->buildMatrixPlan($courses, $admins, $instructors, $students);
        $plain = $password ?: ('QaTesters-'.Str::password(10, symbols: false).'!');
        $accounts = [];
        $seq = 0;

        foreach ($plan as $row) {
            $seq++;
            $email = $row['email_local'].'@'.$domain;
            $nid = $this->nationalIdFor($seq);
            $mobile = $this->mobileFor($seq);

            $user = User::query()->where('email', $email)->first();
            $created = false;

            if (! $user) {
                $this->assertUniqueIdentity($nid, $mobile, $email);
                $user = User::create([
                    'first_name' => 'QA',
                    'second_name' => $row['second_name'],
                    'third_name' => 'Matrix',
                    'email' => $email,
                    'password' => Hash::make($plain),
                    'national_id' => $nid,
                    'mobile_number' => $mobile,
                    'job' => 'QA Tester',
                    'date_of_birth' => '1990-01-01',
                    'profile_photo' => '',
                    'is_verified' => true,
                    'is_superadmin' => false,
                    'registration_completed' => true,
                    'application_status' => User::APPLICATION_STATUS_APPROVED,
                ]);
                $created = true;
            } else {
                $user->forceFill([
                    'first_name' => 'QA',
                    'second_name' => $row['second_name'],
                    'third_name' => 'Matrix',
                    'password' => Hash::make($plain),
                    'is_verified' => true,
                    'is_superadmin' => false,
                    'registration_completed' => true,
                    'application_status' => User::APPLICATION_STATUS_APPROVED,
                ])->save();
            }

            $appliedRoles = [];
            foreach ($row['course_roles'] as $assignment) {
                $cid = (int) $assignment['course_id'];
                $slug = $assignment['slug'];
                $roleId = $this->roleIdFor($cid, $slug);
                if ($roleId === null) {
                    throw ValidationException::withMessages([
                        'role' => "Course #{$cid} is missing role slug [{$slug}].",
                    ]);
                }

                $this->serviceAssigner->ensureMembershipForCourse($user, $cid);
                $this->courseAssigner->assignOrUpdate($user, $cid, $roleId, notify: false);
                $appliedRoles[] = ['course_id' => $cid, 'slug' => $slug, 'role_id' => $roleId];
            }

            foreach ($row['service_admin_ids'] as $serviceId) {
                $this->ensureServiceAdmin($user, (int) $serviceId);
            }

            $accounts[] = [
                'persona' => $row['persona'],
                'index' => $row['index'],
                'email' => $email,
                'user_id' => (int) $user->user_id,
                'created' => $created,
                'course_roles' => $appliedRoles,
                'service_admin_ids' => $row['service_admin_ids'],
                // Back-compat keys for older command/tests
                'slug' => $row['persona'],
                'role_id' => $appliedRoles[0]['role_id'] ?? 0,
            ];
        }

        foreach ($courses as $course) {
            $this->resolver->bumpCoursePermissionsVersion($course);
        }

        $result = [
            'courses' => $courses,
            'course' => $courses->first(),
            'password' => $plain,
            'accounts' => $accounts,
        ];

        if ($writeCredentials) {
            $this->writeCredentialsFile($result, $domain);
        }

        return $result;
    }

    /**
     * @return array{deleted_users: int, emails: list<string>}
     */
    public function wipe(string $domain = self::DEFAULT_EMAIL_DOMAIN): array
    {
        $users = User::query()
            ->where(function ($q) use ($domain) {
                $q->where('email', 'like', 'qa.matrix.%@'.$domain)
                    ->orWhere('email', 'like', 'qa.course.%@'.$domain);
            })
            ->get();

        $emails = [];
        $deleted = 0;

        foreach ($users as $user) {
            $emails[] = $user->email;
            UserCourseRole::query()->where('user_id', $user->user_id)->delete();
            if (ServiceRoleAssignmentService::schemaReady()) {
                UserServiceRole::query()->where('user_id', $user->user_id)->delete();
            }
            $user->delete();
            $deleted++;
        }

        $path = storage_path('app/'.self::CREDENTIALS_RELATIVE_PATH);
        if (is_file($path)) {
            @unlink($path);
        }

        return ['deleted_users' => $deleted, 'emails' => $emails];
    }

    /** @return list<string> */
    public function emails(string $domain = self::DEFAULT_EMAIL_DOMAIN): array
    {
        return User::query()
            ->where(function ($q) use ($domain) {
                $q->where('email', 'like', 'qa.matrix.%@'.$domain)
                    ->orWhere('email', 'like', 'qa.course.%@'.$domain);
            })
            ->pluck('email')
            ->all();
    }

    /**
     * @param  array{
     *   courses: Collection<int, Course>,
     *   password: string,
     *   accounts: list<array<string, mixed>>
     * }  $result
     */
    public function writeCredentialsFile(array $result, string $domain): string
    {
        $lines = [
            '# QA matrix tester accounts (do not commit)',
            '',
            'Generated: '.now()->toIso8601String(),
            'Email domain: '.$domain,
            'Shared password: '.$result['password'],
            'Courses:',
        ];

        foreach ($result['courses'] as $course) {
            $lines[] = '  - #'.$course->course_id.' '.$course->title;
        }

        $lines[] = '';
        $lines[] = '| Persona | Email | user_id | Course roles | Service admin |';
        $lines[] = '|---|---|---|---|---|';

        foreach ($result['accounts'] as $row) {
            $roles = collect($row['course_roles'] ?? [])
                ->map(fn (array $r) => '#'.$r['course_id'].':'.$r['slug'])
                ->implode(', ');
            $svc = implode(',', $row['service_admin_ids'] ?? []);
            $lines[] = '| '.$row['persona'].$row['index'].' | '.$row['email'].' | '.$row['user_id'].' | '.$roles.' | '.$svc.' |';
        }

        $lines[] = '';
        $lines[] = 'Login: '.(string) config('app.url').'/login';
        $lines[] = '';
        $lines[] = 'Wipe later: php artisan qa:course-testers --wipe --force';

        $path = storage_path('app/'.self::CREDENTIALS_RELATIVE_PATH);
        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }

    private function ensureServiceAdmin(User $user, int $serviceId): void
    {
        if (! ServiceRoleAssignmentService::schemaReady()) {
            return;
        }

        $service = ChurchService::query()->find($serviceId);
        if (! $service) {
            return;
        }

        $adminRole = $this->serviceAssigner->adminRoleFor($service);
        $this->serviceAssigner->assign($user, $service, $adminRole, asPrimary: false, allowCrossService: true);
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return list<int>
     */
    private function uniqueServiceIds(Collection $courses): array
    {
        return $courses
            ->pluck('service_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function nationalIdFor(int $seq): string
    {
        return sprintf('2990101999%04d', self::IDENTITY_BASE + $seq);
    }

    private function mobileFor(int $seq): string
    {
        // 11 digits: 010 + 8-digit sequence in reserved QA block
        return sprintf('010%08d', 99900000 + self::IDENTITY_BASE + $seq);
    }

    private function assertUniqueIdentity(string $nationalId, string $mobile, string $email): void
    {
        $collision = User::query()
            ->where(function ($q) use ($nationalId, $mobile, $email) {
                $q->where('national_id', $nationalId)
                    ->orWhere('mobile_number', $mobile)
                    ->orWhere('email', $email);
            })
            ->first();

        if ($collision && $collision->email !== $email) {
            throw ValidationException::withMessages([
                'identity' => "National ID or mobile collides with existing user #{$collision->user_id} ({$collision->email}).",
            ]);
        }
    }
}
