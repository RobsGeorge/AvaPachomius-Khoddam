<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\MoneyIn;
use App\Services\AuditLogService;
use App\Support\Money;
use Illuminate\Http\Request;

class MoneyInController extends Controller
{
    use ResolvesTenantChurch;

    public function index()
    {
        $entries = MoneyIn::query()
            ->orderByDesc('received_at')
            ->orderByDesc('money_in_id')
            ->paginate(30);

        return view('church.finance.money-in.index', compact('entries'));
    }

    public function create()
    {
        return view('church.finance.money-in.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateEntry($request);
        $church = $this->resolveChurch();

        $amount = Money::toMinor($validated['amount']);
        Money::assertNonNegative($amount);

        $entry = new MoneyIn([
            'source' => $validated['source'],
            'category' => $validated['category'],
            'amount_minor' => $amount,
            'currency' => strtoupper($validated['currency']),
            'fx_rate' => $validated['fx_rate'],
            'received_at' => $validated['received_at'],
            'notes' => $validated['notes'] ?? null,
        ]);
        $entry->church_id = $church->church_id;
        $entry->save();

        AuditLogService::recordEvent('money_in.created', [
            'money_in_id' => $entry->money_in_id,
            'church_id' => $church->church_id,
            'amount_minor' => $amount,
        ]);

        return redirect()
            ->route('church.finance.money-in.index')
            ->with('success', __('finance.money_in_saved'));
    }

    public function edit(MoneyIn $entry)
    {
        return view('church.finance.money-in.edit', compact('entry'));
    }

    public function update(Request $request, MoneyIn $entry)
    {
        $validated = $this->validateEntry($request);
        $amount = Money::toMinor($validated['amount']);
        Money::assertNonNegative($amount);

        $entry->update([
            'source' => $validated['source'],
            'category' => $validated['category'],
            'amount_minor' => $amount,
            'currency' => strtoupper($validated['currency']),
            'fx_rate' => $validated['fx_rate'],
            'received_at' => $validated['received_at'],
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLogService::recordEvent('money_in.updated', [
            'money_in_id' => $entry->money_in_id,
            'amount_minor' => $amount,
        ]);

        return redirect()
            ->route('church.finance.money-in.index')
            ->with('success', __('finance.money_in_saved'));
    }

    public function destroy(MoneyIn $entry)
    {
        $id = $entry->money_in_id;
        $entry->delete();

        AuditLogService::recordEvent('money_in.deleted', [
            'money_in_id' => $id,
        ]);

        return redirect()
            ->route('church.finance.money-in.index')
            ->with('success', __('finance.money_in_deleted'));
    }

    private function validateEntry(Request $request): array
    {
        return $request->validate([
            'source' => ['required', 'string', 'max:191'],
            'category' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
            'currency' => ['required', 'string', 'size:3'],
            'fx_rate' => ['required', 'string', 'regex:/^\d+(\.\d{1,12})?$/'],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
