<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChurchApplicationRequest;
use App\Models\ChurchApplication;
use App\Services\ChurchApplicationMailService;
use App\Services\ChurchApplicationService;
use App\Services\ChurchFounderProvisioner;
use Illuminate\Validation\ValidationException;

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
            'selfServe' => ChurchFounderProvisioner::enabled(),
        ]);
    }

    public function store(StoreChurchApplicationRequest $request)
    {
        // Honeypot: bots fill hidden "website"; real users leave it empty.
        // Do not trim here — whitespace-only must still trip (TrimStrings exempts this key).
        if ((string) $request->input('website', '') !== '') {
            return redirect()->route('church-registration.thanks');
        }

        $validated = $request->validated();
        unset($validated['website'], $validated['terms_accepted']);

        $application = ChurchApplication::create([
            ...$validated,
            'status' => ChurchApplication::STATUS_UNVERIFIED,
            'public_token' => ChurchApplication::mintPublicToken(),
            'submitted_at' => now(),
            'email_verified_at' => null,
            'terms_accepted_at' => ChurchFounderProvisioner::enabled() ? now() : null,
        ]);

        $this->mail->sendVerification($application);

        return redirect()->route('church-registration.thanks');
    }

    public function thanks()
    {
        return view('church-registration.thanks', [
            'selfServe' => ChurchFounderProvisioner::enabled(),
        ]);
    }

    public function verify(string $token)
    {
        $application = ChurchApplication::query()
            ->where('public_token', $token)
            ->firstOrFail();

        try {
            $this->applications->verifyEmail($application);
        } catch (ValidationException $e) {
            return redirect()
                ->route('church-registration.status', ['token' => $token])
                ->withErrors($e->errors());
        }

        $application->refresh();
        $flash = ChurchFounderProvisioner::enabled() && $application->isProvisioned()
            ? __('church_applications.email_verified_provisioned')
            : __('church_applications.email_verified');

        return redirect()
            ->route('church-registration.status', ['token' => $token])
            ->with('success', $flash);
    }

    public function status(string $token)
    {
        $application = ChurchApplication::query()
            ->where('public_token', $token)
            ->with('church')
            ->firstOrFail();

        return view('church-registration.status', [
            'application' => $application,
            'selfServe' => ChurchFounderProvisioner::enabled(),
        ]);
    }
}
