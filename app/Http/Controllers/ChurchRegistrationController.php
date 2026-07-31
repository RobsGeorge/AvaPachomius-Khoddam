<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChurchApplicationRequest;
use App\Models\ChurchApplication;
use App\Services\ChurchApplicationMailService;
use App\Services\ChurchApplicationService;

class ChurchRegistrationController extends Controller
{
    public function __construct(
        private ChurchApplicationMailService $mail,
        private ChurchApplicationService $applications,
    ) {}

    public function create()
    {
        return view('church-registration.create', [
            'countries' => config('countries'),
        ]);
    }

    public function store(StoreChurchApplicationRequest $request)
    {
        // Honeypot: bots fill hidden "website"; real users leave it empty.
        if (filled($request->input('website'))) {
            return redirect()->route('church-registration.thanks');
        }

        $validated = $request->validated();
        unset($validated['website']);

        $application = ChurchApplication::create([
            ...$validated,
            'status' => ChurchApplication::STATUS_UNVERIFIED,
            'public_token' => ChurchApplication::mintPublicToken(),
            'submitted_at' => now(),
            'email_verified_at' => null,
        ]);

        $this->mail->sendVerification($application);

        return redirect()->route('church-registration.thanks');
    }

    public function thanks()
    {
        return view('church-registration.thanks');
    }

    public function verify(string $token)
    {
        $application = ChurchApplication::query()
            ->where('public_token', $token)
            ->firstOrFail();

        $this->applications->verifyEmail($application);

        return redirect()
            ->route('church-registration.status', ['token' => $token])
            ->with('success', __('church_applications.email_verified'));
    }

    public function status(string $token)
    {
        $application = ChurchApplication::query()
            ->where('public_token', $token)
            ->firstOrFail();

        return view('church-registration.status', [
            'application' => $application,
        ]);
    }
}
