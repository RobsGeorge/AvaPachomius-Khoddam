<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\Auth\ChurchRegistrationQrService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChurchRegistrationQrController extends Controller
{
    public function __construct(
        private ChurchRegistrationQrService $qr,
    ) {}

    /** Mint / rotate a church registration QR token (permission-gated). */
    public function mint(Request $request)
    {
        Gate::authorize('church.registration_qr.manage');

        $churchId = TenantContext::id() ?? Church::main()?->church_id;
        $church = Church::query()->findOrFail($churchId);

        $result = $this->qr->mint($church, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'organization_id' => $result['payload']['organization_id'],
                'rotating_token' => $result['payload']['rotating_token'],
                'expires_at' => $result['token']->expires_at?->toIso8601String(),
                'scan_url' => route('register.qr.scan', ['token' => $result['plain_token']]),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', __('register.qr_minted'))
            ->with('registration_qr_payload', $result['payload'])
            ->with('registration_qr_scan_url', route('register.qr.scan', ['token' => $result['plain_token']]));
    }

    /** Public QR scan entry: validate token, bind session, open register form. */
    public function scan(string $token)
    {
        $qrToken = $this->qr->findUsable($token);
        if (! $qrToken) {
            $this->qr->clearSession();

            return redirect()
                ->route('register')
                ->withErrors(['general' => __('register.qr_token_invalid')]);
        }

        $this->qr->bindToSession($qrToken);

        return redirect()
            ->route('register')
            ->with('success', __('register.qr_token_accepted'));
    }
}
