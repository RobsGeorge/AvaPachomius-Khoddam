<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\HomeVisit;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HomeVisitController extends Controller
{
    use ResolvesTenantChurch;

    public function index()
    {
        $user = Auth::user();
        $church = $this->resolveChurch();
        $query = HomeVisit::query()->with('assignee')->orderBy('scheduled_at');

        // Church admins / superadmin see all; priests & servants see their assignments.
        if (! ($user->is_superadmin ?? false)
            && ! $user->canInChurch('priest.manage', $church)
            && ! $user->canInChurch('church.configure', $church)
        ) {
            $query->where('assigned_user_id', $user->user_id);
        }

        $visits = $query->paginate(30);

        return view('church.home-visits.index', compact('visits'));
    }

    public function create()
    {
        return view('church.home-visits.create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $church = $this->resolveChurch();

        $validated = $request->validate([
            'subject_name' => ['required', 'string', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'duration_min' => ['nullable', 'integer', 'min:15', 'max:480'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'assigned_user_email' => ['nullable', 'email', 'exists:user,email'],
        ]);

        $assigneeId = $user->user_id;
        if (! empty($validated['assigned_user_email'])
            && (($user->is_superadmin ?? false)
                || $user->canInChurch('priest.manage', $church))
        ) {
            $assigneeId = User::where('email', $validated['assigned_user_email'])->value('user_id') ?: $assigneeId;
        }

        $visit = new HomeVisit([
            'assigned_user_id' => $assigneeId,
            'subject_name' => $validated['subject_name'],
            'address' => $validated['address'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'duration_min' => $validated['duration_min'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => HomeVisit::STATUS_SCHEDULED,
        ]);
        $visit->church_id = $church->church_id;
        $visit->save();

        AuditLogService::recordEvent('home_visit.created', [
            'home_visit_id' => $visit->home_visit_id,
        ]);

        return redirect()
            ->route('church.home-visits.index')
            ->with('success', __('church_mgmt.visit_created'));
    }

    public function edit(HomeVisit $visit)
    {
        $this->assertCanEdit($visit);

        return view('church.home-visits.edit', compact('visit'));
    }

    public function update(Request $request, HomeVisit $visit)
    {
        $this->assertCanEdit($visit);

        $validated = $request->validate([
            'subject_name' => ['required', 'string', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'duration_min' => ['nullable', 'integer', 'min:15', 'max:480'],
            'status' => ['required', Rule::in([
                HomeVisit::STATUS_SCHEDULED,
                HomeVisit::STATUS_DONE,
                HomeVisit::STATUS_CANCELLED,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $visit->update($validated);

        AuditLogService::recordEvent('home_visit.updated', [
            'home_visit_id' => $visit->home_visit_id,
        ]);

        return redirect()
            ->route('church.home-visits.index')
            ->with('success', __('church_mgmt.visit_updated'));
    }

    private function assertCanEdit(HomeVisit $visit): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $church = $visit->church ?? $this->resolveChurch();

        if (($user->is_superadmin ?? false)
            || (int) $visit->assigned_user_id === (int) $user->user_id
            || $user->canInChurch('priest.manage', $church)
        ) {
            return;
        }

        abort(403);
    }
}
