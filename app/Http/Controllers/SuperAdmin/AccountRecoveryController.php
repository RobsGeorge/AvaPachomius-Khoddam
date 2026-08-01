<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\Recovery\SupportRecoveryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Last-resort support recovery UI. Vouches only — person must enter OTP on the new channel.
 */
class AccountRecoveryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $users = collect();

        if ($q !== '') {
            $users = User::query()
                ->where(function ($query) use ($q) {
                    $query->where('email', 'like', '%'.$q.'%')
                        ->orWhere('mobile_number', 'like', '%'.$q.'%')
                        ->orWhere('national_id', $q);
                    if (ctype_digit($q)) {
                        $query->orWhere('user_id', (int) $q);
                    }
                })
                ->orderBy('user_id')
                ->limit(40)
                ->get();
        }

        return view('superadmin.recovery.index', [
            'q' => $q,
            'users' => $users,
        ]);
    }

    public function store(Request $request, SupportRecoveryService $support)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
            'purpose' => ['required', 'in:rebind_mobile,rebind_email'],
            'asserted_value' => ['required', 'string', 'max:255'],
        ]);

        $subject = User::query()->findOrFail($data['user_id']);

        $result = $support->vouchAndSendOtp(
            $request->user(),
            $subject,
            $data['purpose'],
            $data['asserted_value'],
        );

        if (! ($result['ok'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors(['asserted_value' => __('auth.recovery_'.$result['reason'])]);
        }

        return redirect()
            ->route('superadmin.recovery.index', ['q' => $subject->email])
            ->with('success', __('auth.recovery_support_vouched', [
                'challenge' => $result['challenge']->account_recovery_challenge_id,
            ]));
    }
}
