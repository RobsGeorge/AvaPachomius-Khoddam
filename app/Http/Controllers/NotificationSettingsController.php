<?php

namespace App\Http\Controllers;

use App\Models\OtpCode;
use App\Models\UserNotificationReminder;
use App\Services\NotificationPreferenceService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class NotificationSettingsController extends Controller
{
    public function __construct(
        private NotificationPreferenceService $preferences,
        private WhatsAppNotificationService $whatsapp
    ) {}

    public function edit()
    {
        $user = Auth::user();
        $types = $this->preferences->typesForUser($user);
        $preferences = $this->preferences->preferencesForUser($user)->keyBy('type');
        $reminders = UserNotificationReminder::query()
            ->where('user_id', $user->user_id)
            ->orderBy('remind_at')
            ->get();

        return view('notifications.settings', [
            'user' => $user,
            'types' => $types,
            'preferences' => $preferences,
            'reminders' => $reminders,
            'whatsappConfigured' => $this->whatsapp->isConfigured(),
            'mobileVerificationReady' => Schema::hasColumn('user', 'mobile_verified_at'),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->merge([
            'communication_locale' => $request->input('communication_locale') ?: null,
        ]);

        $validated = $request->validate([
            'communication_locale' => ['nullable', 'in:ar,en'],
            'preferences' => ['nullable', 'array'],
        ]);

        if (\App\Services\EmailTemplateCatalog::userCommunicationLocaleColumnReady()) {
            $user->communication_locale = $validated['communication_locale'] ?? null;
            $user->save();
        }

        $this->preferences->save($user, $request->input('preferences', []));

        return back()->with('success', __('notifications.settings_saved'));
    }

    public function storeReminder(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:1000'],
            'remind_at' => ['required', 'date', 'after:now'],
            'recurrence' => ['required', 'in:once,daily,weekly'],
            'portal' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
            'whatsapp' => ['sometimes', 'boolean'],
        ]);

        $channels = [];
        if ($request->boolean('portal', true)) {
            $channels[] = 'portal';
        }
        if ($request->boolean('email')) {
            $channels[] = 'email';
        }
        if ($request->boolean('whatsapp')) {
            $channels[] = 'whatsapp';
        }
        if ($channels === []) {
            $channels = ['portal'];
        }

        UserNotificationReminder::create([
            'user_id' => Auth::user()->user_id,
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'remind_at' => $validated['remind_at'],
            'recurrence' => $validated['recurrence'],
            'channels' => $channels,
        ]);

        return back()->with('success', __('notifications.reminder_created'));
    }

    /** CV1 (narrow slice) — web progressive mobile verify from notification settings. */
    public function sendMobileCode(Request $request)
    {
        abort_unless(Schema::hasColumn('user', 'mobile_verified_at'), 404);

        $user = Auth::user();

        if (! filled($user->mobile_number)) {
            return back()->withErrors(['mobile' => __('notifications.mobile_verify_missing_number')]);
        }

        $key = 'mobile-verify-send-'.$user->user_id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors(['mobile' => __('notifications.mobile_verify_rate_limited')]);
        }

        $code = random_int(100000, 999999);
        OtpCode::updateOrCreate(
            ['user_id' => $user->user_id],
            ['code' => $code, 'expires_at' => now()->addMinutes(10)]
        );

        $result = $this->whatsapp->sendRawText($user, __('notifications.mobile_verification_message', ['code' => $code]));
        RateLimiter::hit($key, 60);

        if (! $result['ok']) {
            return back()->withErrors(['mobile' => __('notifications.mobile_verify_send_failed')]);
        }

        return back()->with('success', __('notifications.mobile_verify_code_sent'));
    }

    public function verifyMobileCode(Request $request)
    {
        abort_unless(Schema::hasColumn('user', 'mobile_verified_at'), 404);

        $user = Auth::user();

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $otp = OtpCode::where('user_id', $user->user_id)
            ->where('code', $validated['code'])
            ->where('expires_at', '>=', now())
            ->first();

        if (! $otp) {
            return back()->withErrors(['code' => __('notifications.mobile_verify_invalid_code')]);
        }

        $otp->delete();

        $user->mobile_verified_at = now();
        if (Schema::hasColumn('user', 'whatsapp_capable')) {
            $user->whatsapp_capable = $request->boolean('whatsapp_capable');
        }
        $user->save();

        return back()->with('success', __('notifications.mobile_verify_success'));
    }

    public function destroyReminder(UserNotificationReminder $reminder)
    {
        abort_unless($reminder->user_id === Auth::user()->user_id, 403);
        $reminder->delete();

        return back()->with('success', __('notifications.reminder_deleted'));
    }
}
