<?php

namespace App\Http\Controllers;

use App\Services\StudentOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function __construct(
        private StudentOnboardingService $onboarding
    ) {}

    public function complete(Request $request)
    {
        $user = Auth::user();

        abort_unless($user && $this->onboarding->shouldShow($user), 403);

        $this->onboarding->complete($user);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}
