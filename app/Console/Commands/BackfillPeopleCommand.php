<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\Person;
use App\Models\User;
use App\Services\People\PersonRegistryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * P2.1 reversible backfill: one Person per existing User (+ reconciliation report).
 * Students/served people are users via roles — no separate student table to backfill.
 */
class BackfillPeopleCommand extends Command
{
    protected $signature = 'people:backfill
                            {--dry-run : Report what would change without writing}
                            {--reverse : Null user.person_id and delete backfilled people (no users left)}
                            {--report=docs/migrations/p2.1-report.md : Reconciliation report path}';

    protected $description = 'Backfill people registry from users (dry-run / reverse / report)';

    public function handle(PersonRegistryService $registry): int
    {
        if (! Schema::hasTable('people') || ! Schema::hasColumn('user', 'person_id')) {
            $this->error('People schema missing — run migrations first.');

            return self::FAILURE;
        }

        if ($this->option('reverse')) {
            return $this->reverse($registry);
        }

        $dryRun = (bool) $this->option('dry-run');
        $churchId = (int) (Church::main()?->church_id ?? 1);

        $users = User::query()->orderBy('user_id')->get();
        $wouldCreate = 0;
        $alreadyLinked = 0;
        $created = 0;

        foreach ($users as $user) {
            if ($user->person_id) {
                $alreadyLinked++;
                continue;
            }

            $wouldCreate++;
            if ($dryRun) {
                continue;
            }

            $registry->ensureForUser($user, $churchId);
            $created++;
        }

        $usersTotal = $users->count();
        $usersWithPerson = User::query()->whereNotNull('person_id')->count();
        $peopleTotal = Person::withoutTenancy()->count();
        $peopleActive = Person::withoutTenancy()->active()->count();
        $orphans = Person::withoutTenancy()
            ->active()
            ->whereDoesntHave('users')
            ->count();

        $reportPath = (string) $this->option('report');
        $this->writeReport($reportPath, [
            'dry_run' => $dryRun,
            'church_id' => $churchId,
            'users_total' => $usersTotal,
            'users_with_person_id' => $usersWithPerson,
            'already_linked' => $alreadyLinked,
            'created_or_would_create' => $dryRun ? $wouldCreate : $created,
            'people_total' => $peopleTotal,
            'people_active' => $peopleActive,
            'active_people_without_user' => $orphans,
            'reconciled' => $usersTotal > 0 && $usersWithPerson === $usersTotal,
        ]);

        $this->info($dryRun
            ? "Dry-run: would create {$wouldCreate} people; {$alreadyLinked} already linked."
            : "Backfill complete: created {$created}; {$alreadyLinked} already linked.");
        $this->line("Report: {$reportPath}");
        $this->line("Users with person_id: {$usersWithPerson}/{$usersTotal}");

        return ($usersWithPerson === $usersTotal || $dryRun) ? self::SUCCESS : self::FAILURE;
    }

    private function reverse(PersonRegistryService $registry): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $linked = User::query()->whereNotNull('person_id')->count();

        if ($dryRun) {
            $this->warn("Dry-run reverse: would clear person_id on {$linked} users and delete orphan people.");

            return self::SUCCESS;
        }

        User::query()->whereNotNull('person_id')->update(['person_id' => null]);

        $deleted = 0;
        Person::withoutTenancy()->orderBy('person_id')->chunkById(100, function ($people) use (&$deleted) {
            foreach ($people as $person) {
                if ($person->users()->exists()) {
                    continue;
                }
                // Skip if family/relationship rows still reference them.
                if ($person->familyMemberships()->exists() || $person->relationships()->exists()) {
                    continue;
                }
                $person->delete();
                $deleted++;
            }
        }, 'person_id');

        $this->info("Reverse complete: cleared user.person_id; deleted {$deleted} orphan people.");

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $stats */
    private function writeReport(string $path, array $stats): void
    {
        $dir = dirname($path);
        if ($dir !== '' && $dir !== '.' && ! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $when = now()->toIso8601String();
        $mode = $stats['dry_run'] ? 'dry-run' : 'committed';
        $ok = $stats['reconciled'] ? 'yes' : ($stats['dry_run'] ? 'n/a (dry-run)' : 'no');

        $md = <<<MD
# P2.1 people registry — reconciliation report

Generated: `{$when}`  
Mode: **{$mode}**

## Counts

| Metric | Value |
|---|---|
| Tenant Zero church_id | {$stats['church_id']} |
| Users total | {$stats['users_total']} |
| Users with person_id | {$stats['users_with_person_id']} |
| Already linked before run | {$stats['already_linked']} |
| People created (or would create) | {$stats['created_or_would_create']} |
| People rows total | {$stats['people_total']} |
| People active | {$stats['people_active']} |
| Active people without user | {$stats['active_people_without_user']} |
| Every user has person_id | {$ok} |

## Notes

- Students / served people are `user` rows (via course/service roles); one person per user covers that population.
- Reversible: `php artisan people:backfill --reverse` (add `--dry-run` to preview).
- Duplicate detection uses `normalized_name` (see `App\\Support\\ArabicNameNormalizer`).

MD;

        File::put(base_path($path), $md);
    }
}
