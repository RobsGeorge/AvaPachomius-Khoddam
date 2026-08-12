<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Services\Finance\PayrollCadenceService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * T6 residual — monthly payroll cadence generator. Iterates every church
 * (console commands have no per-request tenant resolution, so this sets
 * TenantContext explicitly per church, unlike web-request-scoped code).
 */
class GeneratePayrollNextPeriod extends Command
{
    protected $signature = 'payroll:generate-next-period';

    protected $description = "Generate each church's next payroll period as a draft run, copying forward the previous run's payees.";

    public function handle(PayrollCadenceService $cadence): int
    {
        $generated = 0;
        $skipped = 0;

        foreach (Church::query()->get() as $church) {
            TenantContext::set($church);

            $result = $cadence->generateNextPeriod($church);

            if ($result['status'] === PayrollCadenceService::STATUS_GENERATED) {
                $generated++;
                $this->info("Generated payroll run #{$result['run']->payroll_run_id} for church #{$church->church_id}.");
            } else {
                $skipped++;
            }
        }

        TenantContext::clear();
        $this->info("Done. {$generated} run(s) generated, {$skipped} church(es) skipped (no prior run or already generated).");

        return self::SUCCESS;
    }
}
