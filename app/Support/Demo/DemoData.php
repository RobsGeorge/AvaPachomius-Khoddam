<?php

namespace App\Support\Demo;

use App\Models\Church;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared identity + teardown for the staging demo dataset. Every demo row is tagged so it
 * can be found and removed without ever touching real data:
 *   - churches  → slug starts with the demo prefix (and settings.demo = true)
 *   - users     → email ends with @{email_domain}
 *   - all other rows are church-scoped under a demo church (church_id), so they fall out
 *     when we delete by the demo church ids; user-linked global rows fall out by user id.
 *
 * The seeder (DemoDataSeeder) writes; this class defines the markers and the wipe.
 */
class DemoData
{
    public static function churchSlugPrefix(): string
    {
        return (string) config('demo.church_slug_prefix', 'demo-');
    }

    public static function emailDomain(): string
    {
        return (string) config('demo.email_domain', 'demo.khedma.test');
    }

    public static function password(): string
    {
        return (string) config('demo.password', 'Demo1234!');
    }

    /** An email in the demo namespace, e.g. demoEmail('admin.stmark') → admin.stmark@demo.khedma.test */
    public static function email(string $localPart): string
    {
        return $localPart.'@'.self::emailDomain();
    }

    /** A church slug in the demo namespace, e.g. demoSlug('stmark') → demo-stmark */
    public static function slug(string $name): string
    {
        return self::churchSlugPrefix().$name;
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    public static function churchIds(): \Illuminate\Support\Collection
    {
        return Church::query()
            ->where('slug', 'like', self::churchSlugPrefix().'%')
            ->pluck('church_id');
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    public static function userIds(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('email', 'like', '%@'.self::emailDomain())
            ->pluck('user_id');
    }

    public static function exists(): bool
    {
        return self::churchIds()->isNotEmpty() || self::userIds()->isNotEmpty();
    }

    /**
     * Delete every demo row, in any environment, by marker only. Returns a per-scope
     * count summary. Foreign-key checks are disabled around the sweep so table order
     * does not matter; nothing outside the demo markers is ever touched.
     *
     * @return array<string, int>
     */
    public static function wipe(): array
    {
        $churchIds = self::churchIds()->all();
        $userIds = self::userIds()->all();

        // Capture the demo churches' organization rows before the churches are deleted.
        $orgIds = Church::query()
            ->whereIn('church_id', $churchIds)
            ->pluck('organization_id')
            ->filter()
            ->values()
            ->all();

        $deleted = ['churches' => 0, 'users' => 0, 'organizations' => 0, 'scoped_rows' => 0, 'user_rows' => 0];

        if (empty($churchIds) && empty($userIds)) {
            return $deleted;
        }

        Schema::disableForeignKeyConstraints();

        try {
            $deleted['scoped_rows'] += self::wipeUnscopedChildren($churchIds, $userIds);

            foreach (self::tableNames() as $table) {
                if ($table === 'migrations') {
                    continue;
                }

                // Church-scoped rows (this also removes the demo churches themselves,
                // whose PK is church_id) — but skip the church table here so we can count it.
                if (! empty($churchIds) && $table !== 'church' && Schema::hasColumn($table, 'church_id')) {
                    $deleted['scoped_rows'] += DB::table($table)->whereIn('church_id', $churchIds)->delete();
                }

                // User-linked global rows (roles, system roles, preferences, …) — skip the
                // user table here so we can count it, and skip church-scoped tables already
                // swept above to avoid double counting.
                if (! empty($userIds)
                    && $table !== 'user'
                    && ! Schema::hasColumn($table, 'church_id')
                    && Schema::hasColumn($table, 'user_id')) {
                    $deleted['user_rows'] += DB::table($table)->whereIn('user_id', $userIds)->delete();
                }
            }

            // Personal access tokens are polymorphic (tokenable_id / tokenable_type).
            if (! empty($userIds) && Schema::hasTable('personal_access_tokens')) {
                $deleted['user_rows'] += DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();
            }

            if (! empty($userIds)) {
                $deleted['users'] = DB::table('user')->whereIn('user_id', $userIds)->delete();
            }

            if (! empty($churchIds)) {
                $deleted['churches'] = DB::table('church')->whereIn('church_id', $churchIds)->delete();
            }

            if (! empty($orgIds) && Schema::hasTable('organizations')) {
                $deleted['organizations'] = DB::table('organizations')
                    ->whereIn('organization_id', $orgIds)
                    ->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        AuditLogService::recordEvent('demo.wiped', $deleted);

        return $deleted;
    }

    /**
     * Rows that carry neither a demo church_id nor a user_id (exam questions/options/answers,
     * survey questions/answers, curriculum pivots, role grants, family members) are reachable
     * only through a demo parent, so resolve them by parent id before the church_id sweep
     * deletes those parents. Demo users' people rows are removed by user.person_id because a
     * user created before joining a church gets its person stamped with Tenant Zero.
     *
     * @param  list<int>  $churchIds
     * @param  list<int>  $userIds
     */
    private static function wipeUnscopedChildren(array $churchIds, array $userIds): int
    {
        $personIds = self::idsWhereIn('user', 'person_id', 'user_id', $userIds);
        $deleted = self::deleteWhereIn('family_members', 'person_id', $personIds)
            + self::deleteWhereIn('people', 'person_id', $personIds);

        if (empty($churchIds)) {
            return $deleted;
        }

        $courseIds = self::idsWhereIn('course', 'course_id', 'church_id', $churchIds);
        $serviceIds = self::idsWhereIn('service', 'service_id', 'church_id', $churchIds);
        $roleIds = array_values(array_unique(array_merge(
            self::idsWhereIn('roles', 'role_id', 'church_id', $churchIds),
            self::idsWhereIn('roles', 'role_id', 'course_id', $courseIds),
            self::idsWhereIn('roles', 'role_id', 'service_id', $serviceIds),
        )));
        $familyIds = self::idsWhereIn('families', 'family_id', 'church_id', $churchIds);

        $examIds = self::idsWhereIn('exams', 'exam_id', 'church_id', $churchIds);
        $examQuestionIds = self::idsWhereIn('exam_questions', 'question_id', 'exam_id', $examIds);
        $attemptIds = self::idsWhereIn('exam_attempts', 'attempt_id', 'exam_id', $examIds);
        $surveyIds = self::idsWhereIn('feedback_surveys', 'survey_id', 'church_id', $churchIds);
        $feedbackQuestionIds = self::idsWhereIn('feedback_questions', 'question_id', 'survey_id', $surveyIds);
        $submissionIds = self::idsWhereIn('feedback_submissions', 'submission_id', 'survey_id', $surveyIds);
        $moduleIds = self::idsWhereIn('modules', 'module_id', 'church_id', $churchIds);

        return $deleted
            + self::deleteWhereIn('role_permission', 'role_id', $roleIds)
            + self::deleteWhereIn('family_members', 'family_id', $familyIds)
            + self::deleteWhereIn('exam_answers', 'attempt_id', $attemptIds)
            + self::deleteWhereIn('exam_answers', 'question_id', $examQuestionIds)
            + self::deleteWhereIn('exam_question_options', 'question_id', $examQuestionIds)
            + self::deleteWhereIn('exam_questions', 'exam_id', $examIds)
            + self::deleteWhereIn('exam_schedules', 'exam_id', $examIds)
            + self::deleteWhereIn('exam_attempts', 'exam_id', $examIds)
            + self::deleteWhereIn('exam_results', 'exam_id', $examIds)
            + self::deleteWhereIn('feedback_answers', 'submission_id', $submissionIds)
            + self::deleteWhereIn('feedback_answers', 'question_id', $feedbackQuestionIds)
            + self::deleteWhereIn('feedback_questions', 'survey_id', $surveyIds)
            + self::deleteWhereIn('feedback_submissions', 'survey_id', $surveyIds)
            + self::deleteWhereIn('course_module', 'course_id', $courseIds)
            + self::deleteWhereIn('module_session', 'module_id', $moduleIds);
    }

    /**
     * @param  list<int>  $values
     * @return list<int>
     */
    private static function idsWhereIn(string $table, string $key, string $column, array $values): array
    {
        if (empty($values) || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)->whereIn($column, $values)->pluck($key)->map(fn ($id) => (int) $id)->all();
    }

    /** @param  list<int>  $values */
    private static function deleteWhereIn(string $table, string $column, array $values): int
    {
        if (empty($values) || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $deleted = 0;
        foreach (array_chunk($values, 1000) as $chunk) {
            $deleted += DB::table($table)->whereIn($column, $chunk)->delete();
        }

        return $deleted;
    }

    /**
     * All table names for the current connection (driver-agnostic, no doctrine/dbal).
     *
     * @return list<string>
     */
    private static function tableNames(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            return array_map(
                fn ($row) => $row->name,
                $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
            );
        }

        // MySQL / MariaDB (staging).
        $key = 'Tables_in_'.$connection->getDatabaseName();

        return array_map(fn ($row) => $row->$key, $connection->select('SHOW TABLES'));
    }
}
