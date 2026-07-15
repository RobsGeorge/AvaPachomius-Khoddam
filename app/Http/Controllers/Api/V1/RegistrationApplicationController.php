<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrationApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationApplicationController extends Controller
{
    public function __construct(
        private RegistrationApplicationService $applications,
    ) {}

    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $application = $this->applications->latestForUser($user);

        return response()->json([
            'data' => [
                'user_application_status' => $user->application_status,
                'application' => $application ? [
                    'id' => $application->id ?? $application->getKey(),
                    'status' => $application->status,
                    'submitted_at' => $application->submitted_at?->toIso8601String()
                        ?? $application->created_at?->toIso8601String(),
                    'needs_correction' => $application->status === \App\Models\RegistrationApplication::STATUS_NEEDS_CORRECTION,
                ] : null,
            ],
        ]);
    }
}
