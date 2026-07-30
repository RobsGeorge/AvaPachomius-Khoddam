<?php

namespace App\Services\Finance;

use App\Models\Church;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Services\AuditLogService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * T6 residual — payroll cadence automation. Generates the next period's DRAFT
 * run by copying forward the previous run's payees/amounts, so an admin
 * reviews and adjusts rather than retyping every payee from scratch. Never
 * auto-finalizes (mirrors the "no silent auto-promote" caution already used
 * for the T9 End-of-Cycle wizard).
 */
class PayrollCadenceService
{
    public const STATUS_GENERATED = 'generated';

    public const STATUS_NO_PREVIOUS_RUN = 'no_previous_run';

    public const STATUS_ALREADY_EXISTS = 'already_exists';

    /**
     * @return array{status: string, run?: PayrollRun}
     */
    public function generateNextPeriod(Church $church): array
    {
        // Anchor on the latest FINALIZED run only: cadence should extend from confirmed
        // numbers, and must not race past an already-pending draft for the next period.
        $previous = PayrollRun::query()
            ->where('status', PayrollRun::STATUS_FINALIZED)
            ->orderByDesc('period_end')
            ->orderByDesc('payroll_run_id')
            ->first();

        if (! $previous) {
            return ['status' => self::STATUS_NO_PREVIOUS_RUN];
        }

        $nextStart = $previous->period_end->copy()->addDay();
        $nextEnd = $nextStart->copy()->addMonthNoOverflow()->subDay();

        $overlapping = PayrollRun::query()
            ->where('period_start', '<=', $nextEnd)
            ->where('period_end', '>=', $nextStart)
            ->exists();

        if ($overlapping) {
            return ['status' => self::STATUS_ALREADY_EXISTS];
        }

        $lineCount = $previous->lines()->count();

        $run = DB::transaction(function () use ($church, $previous, $nextStart, $nextEnd) {
            $run = new PayrollRun([
                'period_start' => $nextStart,
                'period_end' => $nextEnd,
                'status' => PayrollRun::STATUS_DRAFT,
                'currency' => $previous->currency,
                'notes' => null,
            ]);
            $run->church_id = $church->church_id;
            $run->save();

            foreach ($previous->lines as $previousLine) {
                $line = new PayrollLine([
                    'payroll_run_id' => $run->payroll_run_id,
                    'user_id' => $previousLine->user_id,
                    'gross_minor' => $previousLine->gross_minor,
                    'deductions_minor' => $previousLine->deductions_minor,
                    'net_minor' => Money::net($previousLine->gross_minor, $previousLine->deductions_minor),
                    'currency' => $run->currency,
                    'fx_rate' => $previousLine->fx_rate,
                    'notes' => $previousLine->notes,
                ]);
                $line->church_id = $church->church_id;
                $line->save();
            }

            return $run;
        });

        AuditLogService::recordEvent('payroll_run.generated', [
            'payroll_run_id' => $run->payroll_run_id,
            'source_payroll_run_id' => $previous->payroll_run_id,
            'church_id' => $church->church_id,
            'lines_copied' => $lineCount,
        ]);

        return ['status' => self::STATUS_GENERATED, 'run' => $run];
    }
}
