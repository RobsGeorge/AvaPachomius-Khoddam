<?php

namespace App\Http\Controllers;

use App\Models\ChurchService;
use App\Services\ServiceContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceContextController extends Controller
{
    public function __construct(
        private ServiceContextService $serviceContext,
    ) {}

    public function show(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $services = $this->serviceContext->selectableServices($user);
        $current = $this->serviceContext->currentService($user);

        return view('services.select', [
            'services' => $services,
            'currentService' => $current,
            'intended' => $request->query('intended'),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'service_id' => 'required|integer|exists:service,service_id',
            'intended' => 'nullable|string',
        ]);

        $this->serviceContext->setCurrentService($user, (int) $validated['service_id']);

        $intended = $validated['intended'] ?? null;
        if ($intended && $this->isSafeLocalRedirect($intended)) {
            return redirect($intended)->with('success', __('service.context_selected'));
        }

        return redirect()
            ->route('dashboard')
            ->with('success', __('service.context_selected'));
    }

    public function clear(Request $request)
    {
        $user = Auth::user();
        abort_unless($user && ($user->is_superadmin ?? false), 403);

        $this->serviceContext->clearCurrentService();

        $intended = $request->input('intended');
        if (is_string($intended) && $this->isSafeLocalRedirect($intended)) {
            return redirect($intended)->with('success', __('service.system_wide_mode'));
        }

        return redirect()
            ->route('superadmin.index')
            ->with('success', __('service.system_wide_mode'));
    }

    private function isSafeLocalRedirect(string $url): bool
    {
        if (! str_starts_with($url, '/')) {
            return false;
        }

        return ! str_starts_with($url, '//');
    }
}
