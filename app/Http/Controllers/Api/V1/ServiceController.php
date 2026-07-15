<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChurchService;
use App\Models\ServiceApplication;
use App\Models\User;
use App\Services\ServiceApplicationService;
use App\Services\ServiceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private ServiceContextService $serviceContext,
        private ServiceApplicationService $applications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $services = $this->serviceContext->selectableServices($user);

        return response()->json([
            'data' => $services->map(function ($service) {
                return [
                    'service_id' => $service->service_id,
                    'name' => method_exists($service, 'localizedName')
                        ? $service->localizedName()
                        : ($service->name ?? $service->title),
                    'status' => $service->status ?? null,
                ];
            })->values(),
        ]);
    }

    public function apply(Request $request, ChurchService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $application = $this->applications->submit($user, $service, [
            'message' => $validated['message'] ?? '',
        ]);

        return response()->json([
            'data' => [
                'service_application_id' => $application->service_application_id ?? $application->getKey(),
                'status' => $application->status,
            ],
        ], 201);
    }

    public function applicationStatus(Request $request, ChurchService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $application = ServiceApplication::query()
            ->where('user_id', $user->user_id)
            ->where('service_id', $service->service_id)
            ->latest('service_application_id')
            ->first();

        return response()->json([
            'data' => [
                'service_id' => $service->service_id,
                'application' => $application ? [
                    'service_application_id' => $application->service_application_id ?? $application->getKey(),
                    'status' => $application->status,
                    'submitted_at' => $application->submitted_at?->toIso8601String()
                        ?? $application->created_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}
