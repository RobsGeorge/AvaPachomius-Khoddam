<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    use ResolvesTenantChurch;

    public function index()
    {
        $runs = PayrollRun::query()
            ->withCount('lines')
            ->orderByDesc('period_end')
            ->orderByDesc('payroll_run_id')
            ->paginate(30);

        return view('church.finance.payroll.index', compact('runs'));
    }

    public function create()
    {
        return view('church.finance.payroll.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $church = $this->resolveChurch();
        $run = new PayrollRun([
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'status' => PayrollRun::STATUS_DRAFT,
            'currency' => strtoupper($validated['currency']),
            'notes' => $validated['notes'] ?? null,
        ]);
        $run->church_id = $church->church_id;
        $run->save();

        AuditLogService::recordEvent('payroll_run.created', [
            'payroll_run_id' => $run->payroll_run_id,
            'church_id' => $church->church_id,
        ]);

        return redirect()
            ->route('church.finance.payroll.show', $run)
            ->with('success', __('finance.run_created'));
    }

    public function show(PayrollRun $run)
    {
        $run->load(['lines.user']);

        return view('church.finance.payroll.show', compact('run'));
    }

    public function storeLine(Request $request, PayrollRun $run)
    {
        abort_unless($run->isDraft(), 422, __('finance.run_locked'));

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:user,email'],
            'gross' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
            'deductions' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();
        $gross = Money::toMinor($validated['gross']);
        $deductions = Money::toMinor($validated['deductions'] ?? '0');
        Money::assertNonNegative($gross, 'gross');
        Money::assertNonNegative($deductions, 'deductions');
        $net = Money::net($gross, $deductions);
        if ($net < 0) {
            throw ValidationException::withMessages([
                'deductions' => __('finance.deductions_exceed_gross'),
            ]);
        }

        $exists = PayrollLine::query()
            ->where('payroll_run_id', $run->payroll_run_id)
            ->where('user_id', $user->user_id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'email' => __('finance.line_exists'),
            ]);
        }

        $line = new PayrollLine([
            'payroll_run_id' => $run->payroll_run_id,
            'user_id' => $user->user_id,
            'gross_minor' => $gross,
            'deductions_minor' => $deductions,
            'net_minor' => $net,
            'currency' => $run->currency,
            'fx_rate' => Money::DEFAULT_FX_RATE,
            'notes' => $validated['notes'] ?? null,
        ]);
        $line->church_id = $run->church_id;
        $line->save();

        AuditLogService::recordEvent('payroll_line.created', [
            'payroll_line_id' => $line->payroll_line_id,
            'payroll_run_id' => $run->payroll_run_id,
        ]);

        return back()->with('success', __('finance.line_saved'));
    }

    public function destroyLine(PayrollRun $run, PayrollLine $line)
    {
        abort_unless($run->isDraft(), 422, __('finance.run_locked'));
        abort_unless((int) $line->payroll_run_id === (int) $run->payroll_run_id, 404);

        $lineId = $line->payroll_line_id;
        $line->delete();

        AuditLogService::recordEvent('payroll_line.deleted', [
            'payroll_line_id' => $lineId,
            'payroll_run_id' => $run->payroll_run_id,
        ]);

        return back()->with('success', __('finance.line_deleted'));
    }

    public function finalize(PayrollRun $run)
    {
        abort_unless($run->isDraft(), 422, __('finance.run_locked'));
        abort_unless($run->lines()->exists(), 422, __('finance.run_empty'));

        $run->update(['status' => PayrollRun::STATUS_FINALIZED]);

        AuditLogService::recordEvent('payroll_run.finalized', [
            'payroll_run_id' => $run->payroll_run_id,
        ]);

        return redirect()
            ->route('church.finance.payroll.show', $run)
            ->with('success', __('finance.run_finalized'));
    }

    public function destroy(PayrollRun $run)
    {
        abort_unless($run->isDraft(), 422, __('finance.run_locked'));

        $runId = $run->payroll_run_id;
        DB::transaction(function () use ($run) {
            $run->lines()->delete();
            $run->delete();
        });

        AuditLogService::recordEvent('payroll_run.deleted', [
            'payroll_run_id' => $runId,
        ]);

        return redirect()
            ->route('church.finance.payroll.index')
            ->with('success', __('finance.run_deleted'));
    }
}
