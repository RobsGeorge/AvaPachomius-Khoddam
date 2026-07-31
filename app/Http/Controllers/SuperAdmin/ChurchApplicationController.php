<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ChurchApplication;
use App\Services\ChurchApplicationService;
use Illuminate\Http\Request;

class ChurchApplicationController extends Controller
{
    public function __construct(
        private ChurchApplicationService $applications,
    ) {}

    public function index()
    {
        $user = auth()->user();
        abort_unless($user && ($user->is_superadmin ?? false), 403);

        // Unverified rows stay out of the review queue until the contact confirms email.
        $applications = ChurchApplication::query()
            ->whereIn('status', ChurchApplication::reviewableStatuses())
            ->orderByDesc('submitted_at')
            ->paginate(30);

        return view('superadmin.church-applications.index', compact('applications'));
    }

    public function show(ChurchApplication $churchApplication)
    {
        $user = auth()->user();
        abort_unless($user && ($user->is_superadmin ?? false), 403);

        $churchApplication->load('reviewer');

        return view('superadmin.church-applications.show', [
            'application' => $churchApplication,
        ]);
    }

    public function approve(Request $request, ChurchApplication $churchApplication)
    {
        $user = auth()->user();
        abort_unless($user && ($user->is_superadmin ?? false), 403);

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->applications->approve($churchApplication, $user, $validated['admin_note'] ?? null);

        return redirect()
            ->route('superadmin.church-applications.show', $churchApplication)
            ->with('success', __('church_applications.approved'));
    }

    public function reject(Request $request, ChurchApplication $churchApplication)
    {
        $user = auth()->user();
        abort_unless($user && ($user->is_superadmin ?? false), 403);

        $request->merge([
            'admin_note' => trim((string) $request->input('admin_note', '')),
        ]);

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:2000'],
        ], [
            'admin_note.required' => __('church_applications.validation_admin_note_required'),
            'admin_note.max' => __('church_applications.validation_max', [
                'attribute' => __('church_applications.admin_note_required'),
            ]),
        ]);

        $this->applications->reject($churchApplication, $user, $validated['admin_note']);

        return redirect()
            ->route('superadmin.church-applications.index')
            ->with('success', __('church_applications.rejected'));
    }
}
