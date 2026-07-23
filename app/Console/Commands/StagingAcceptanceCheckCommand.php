<?php

namespace App\Console\Commands;

use App\Services\Tenancy\StagingAcceptanceChecker;
use Illuminate\Console\Command;

/**
 * Automated gate for docs/staging-acceptance-checklist.md (T7 + T8).
 */
class StagingAcceptanceCheckCommand extends Command
{
    protected $signature = 'tenancy:acceptance-check
                            {--t7 : Run T7 tenancy checks only}
                            {--t8 : Run T8 structure checks only}
                            {--expect-multi-tenant : Fail when MULTI_TENANT=false}
                            {--repair-orgs : Repair missing church ↔ organizations links}
                            {--pilot-slug=pilot-service : Expected pilot church slug}';

    protected $description = 'Run staging acceptance checks for T7 cutover and T8 structure (see docs/staging-acceptance-checklist.md)';

    public function handle(StagingAcceptanceChecker $checker): int
    {
        $runT7 = $this->option('t7') || ! $this->option('t8');
        $runT8 = $this->option('t8') || ! $this->option('t7');
        $expectMulti = (bool) $this->option('expect-multi-tenant');
        $repairOrgs = (bool) $this->option('repair-orgs');
        $pilotSlug = (string) $this->option('pilot-slug');

        $all = [];

        $this->line('Staging acceptance — see docs/staging-acceptance-checklist.md');
        $this->newLine();

        if ($runT7) {
            $this->info('=== T7 tenancy cutover ===');
            $t7 = $checker->runT7($expectMulti, $pilotSlug, $repairOrgs);
            $this->renderResults($t7);
            $all = array_merge($all, $t7);
            $this->newLine();
        }

        if ($runT8) {
            $this->info('=== T8 structure expand ===');
            $t8 = $checker->runT8();
            $this->renderResults($t8);
            $all = array_merge($all, $t8);
            $this->newLine();
        }

        if ($checker->hasFailures($all)) {
            $this->error('Acceptance check FAILED — fix items marked FAIL above.');
            $this->line('Manual steps: docs/staging-acceptance-checklist.md');

            return self::FAILURE;
        }

        $warnCount = count(array_filter($all, fn ($r) => $r['status'] === StagingAcceptanceChecker::STATUS_WARN));
        if ($warnCount > 0) {
            $this->warn("Acceptance check passed with {$warnCount} warning(s). Complete manual checklist before production.");
        } else {
            $this->info('Acceptance check PASSED.');
        }

        $this->line('Next: php vendor/bin/phpunit tests/Feature/Tenancy tests/Feature/Structure');

        return self::SUCCESS;
    }

    /** @param  list<array{name: string, status: string, message: string}>  $rows */
    private function renderResults(array $rows): void
    {
        foreach ($rows as $row) {
            $label = strtoupper($row['status']);
            $line = "[{$label}] {$row['message']}";
            match ($row['status']) {
                StagingAcceptanceChecker::STATUS_FAIL => $this->error($line),
                StagingAcceptanceChecker::STATUS_WARN => $this->warn($line),
                default => $this->line($line),
            };
        }
    }
}
