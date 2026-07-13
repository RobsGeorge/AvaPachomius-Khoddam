<?php

namespace App\Http\Controllers;

use App\Services\ServiceContextService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;

class ServiceRosterController extends Controller
{
    public function __construct(
        private StudentRosterService $rosterService,
        private ServiceContextService $serviceContext,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $services = $this->rosterService->accessibleServices($user);
        $service = $this->serviceContext->resolveAccessibleService(
            $user,
            $request->input('service')
        );

        if (! $service || $services->isEmpty()) {
            return view('services.roster', [
                'services' => $services,
                'service' => null,
                'members' => collect(),
            ]);
        }

        $this->rosterService->authorizeService($user, $service);
        $members = $this->rosterService->serviceMembers($service);

        return view('services.roster', compact('services', 'service', 'members'));
    }
}
