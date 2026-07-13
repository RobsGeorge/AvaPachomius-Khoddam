<?php

namespace App\Http\Controllers;

use App\Models\ChurchService;
use App\Models\ServiceApplication;
use App\Services\ServiceApplicationService;
use Illuminate\Http\Request;

class ServiceApplicationController extends Controller
{
    public function __construct(
        private ServiceApplicationService $applications,
    ) {}

    public function apply(ChurchService $service)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $form = $this->applications->ensureForm($service);

        return view('services.apply', compact('service', 'form'));
    }

    public function store(Request $request, ChurchService $service)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->applications->submit($user, $service, [
            'message' => $validated['message'] ?? '',
        ]);

        return redirect()
            ->route('services.application.status', $service)
            ->with('success', __('service.application_submitted'));
    }

    public function status(ChurchService $service)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $application = ServiceApplication::query()
            ->where('user_id', $user->user_id)
            ->where('service_id', $service->service_id)
            ->latest('service_application_id')
            ->first();

        return view('services.application-status', compact('service', 'application'));
    }
}
