<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent expand backfill: attendance.person_id ← user.person_id where resolvable.
 * Unresolvable rows are reported (never silently skipped or wrongly linked).
 */
class BackfillAttendancePersonIdCommand extends Command
{
    protected $signature = 'attendance:backfill-person-id
                            {--dry-run : Report what would change without writing}
                            {--report=docs/migrations/attendance-person-id-report.md : Reconciliation report path}';

    protected $description = 'Backfill attendance.person_id from linked user.person_id (idempotent)';

    public function handle(): int
    {
        if (! Schema::hasTable('attendance') || ! Schema::hasColumn('attendance', 'person_id')) {
            $this->error('attendance.person_id missing — run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $already = 0;
        $unresolvable = [];

        Attendance::query()
            ->orderBy('attendance_id')
            ->chunkById(200, function ($rows) use ($dryRun, &$updated, &$already, &$unresolvable) {
                foreach ($rows as $row) {
                    if ($row->person_id) {
                        $already++;

                        continue;
                    }

                    if (! $row->user_id) {
                        $unresolvable[] = [
                            'attendance_id' => (int) $row->attendance_id,
                            'user_id' => null,
                            'reason' => 'no_user_id',
                        ];

                        continue;
                    }

                    $personId = User::query()
                        ->where('user_id', $row->user_id)
                        ->value('person_id');

                    if (! $personId) {
                        $unresolvable[] = [
                            'attendance_id' => (int) $row->attendance_id,
                            'user_id' => (int) $row->user_id,
                            'reason' => 'user_has_no_person_id',
                        ];

                        continue;
                    }

                    if (! $dryRun) {
                        Attendance::query()
                            ->where('attendance_id', $row->attendance_id)
                            ->whereNull('person_id')
                            ->update(['person_id' => (int) $personId]);
                    }

                    $updated++;
                }
            }, 'attendance_id');

        $reportPath = (string) $this->option('report');
        $this->writeReport($reportPath, [
            'dry_run' => $dryRun,
            'updated_or_would_update' => $updated,
            'already_linked' => $already,
            'unresolvable_count' => count($unresolvable),
            'unresolvable' => $unresolvable,
        ]);

        if ($unresolvable !== []) {
            $this->warn('Unresolvable attendance rows (actionable — do not ignore):');
            foreach (array_slice($unresolvable, 0, 50) as $item) {
                $this->line(sprintf(
                    '  attendance_id=%d user_id=%s reason=%s',
                    $item['attendance_id'],
                    $item['user_id'] === null ? 'null' : (string) $item['user_id'],
                    $item['reason']
                ));
            }
            if (count($unresolvable) > 50) {
                $this->line('  … '.(count($unresolvable) - 50).' more (see report)');
            }
        }

        $this->info($dryRun
            ? "Dry-run: would update {$updated}; {$already} already linked; ".count($unresolvable).' unresolvable.'
            : "Backfill complete: updated {$updated}; {$already} already linked; ".count($unresolvable).' unresolvable.');
        $this->line("Report: {$reportPath}");

        if (! $dryRun) {
            AuditLogService::recordEvent('attendance.person_id_backfill', [
                'updated' => $updated,
                'already_linked' => $already,
                'unresolvable_count' => count($unresolvable),
            ]);
        }

        return ($unresolvable === [] || $dryRun) ? self::SUCCESS : self::FAILURE;
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
        $lines = [];
        foreach ($stats['unresolvable'] as $item) {
            $uid = $item['user_id'] === null ? 'null' : (string) $item['user_id'];
            $lines[] = "| {$item['attendance_id']} | {$uid} | {$item['reason']} |";
        }
        $tableBody = $lines === [] ? '| — | — | none |' : implode("\n", $lines);

        $md = <<<MD
# Attendance person_id backfill — reconciliation report

Generated: `{$when}`  
Mode: **{$mode}**

## Counts

| Metric | Value |
|---|---|
| Updated (or would update) | {$stats['updated_or_would_update']} |
| Already had person_id | {$stats['already_linked']} |
| Unresolvable | {$stats['unresolvable_count']} |

## Unresolvable rows (actionable)

| attendance_id | user_id | reason |
|---|---|---|
{$tableBody}

## Notes

- Idempotent: re-run is safe; rows with person_id set are skipped.
- Unresolvable rows are never linked to a guessed Person — fix `user.person_id` (e.g. `people:backfill`) then re-run.
- Command: `php artisan attendance:backfill-person-id [--dry-run]`

MD;

        File::put(base_path($path), $md);
    }
}
