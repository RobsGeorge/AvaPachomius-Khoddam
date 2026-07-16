<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\Priest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriestController extends Controller
{
    use ResolvesTenantChurch;

    public function index()
    {
        $priests = Priest::query()
            ->with('user')
            ->orderBy('status')
            ->orderBy('priest_id')
            ->get();

        return view('church.priests.index', compact('priests'));
    }

    public function create()
    {
        return view('church.priests.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:user,email'],
            'title' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in([Priest::STATUS_ACTIVE, Priest::STATUS_INACTIVE])],
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();
        $church = $this->resolveChurch();

        $priest = Priest::query()
            ->where('user_id', $user->user_id)
            ->where('church_id', $church->church_id)
            ->first();

        if (! $priest) {
            $priest = new Priest;
            $priest->church_id = $church->church_id;
            $priest->user_id = $user->user_id;
        }

        $priest->fill([
            'title' => $validated['title'] ?? null,
            'status' => $validated['status'],
        ]);
        $priest->save();

        AuditLogService::recordEvent('priest.saved', [
            'priest_id' => $priest->priest_id,
            'user_id' => $user->user_id,
            'church_id' => $church->church_id,
        ]);

        return redirect()
            ->route('church.priests.index')
            ->with('success', __('church_mgmt.priest_saved'));
    }

    public function edit(Priest $priest)
    {
        $priest->load('user');

        return view('church.priests.edit', compact('priest'));
    }

    public function update(Request $request, Priest $priest)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in([Priest::STATUS_ACTIVE, Priest::STATUS_INACTIVE])],
        ]);

        $priest->update($validated);

        AuditLogService::recordEvent('priest.updated', ['priest_id' => $priest->priest_id]);

        return redirect()
            ->route('church.priests.index')
            ->with('success', __('church_mgmt.priest_saved'));
    }
}
