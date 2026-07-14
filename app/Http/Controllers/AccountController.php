<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotificationReminder;
use App\Services\NotificationPreferenceService;
use App\Support\PasswordRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * F-03 — Self-service account center. One discoverable place that ties together
 * profile, first-class password change, notification preferences, appearance
 * (theme/locale), and a personal data export. Reuses the existing preference
 * controllers/routes; only the password change and the data export are new here.
 */
class AccountController extends Controller
{
    public function index(NotificationPreferenceService $preferences): \Illuminate\View\View
    {
        $user = $this->currentUser();

        $fullName = User::fullNameFromParts(
            $user->first_name ?? '',
            $user->second_name ?? '',
            $user->third_name ?? ''
        );

        return view('account.index', [
            'user' => $user,
            'fullName' => $fullName,
            'notificationTypeCount' => count($preferences->typesForUser($user)),
            'supportedLocales' => config('translation.supported_locales', ['ar', 'en']),
        ]);
    }

    public function updatePassword(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $this->currentUser();

        $request->validate(
            ['current_password' => ['required', 'string'], 'password' => PasswordRules::field()],
            PasswordRules::messages()
        );

        if (! Hash::check((string) $request->input('current_password'), (string) $user->password)) {
            // Surface as a validation error so the audit middleware records the failed trial.
            throw ValidationException::withMessages([
                'current_password' => [__('account.password_current_incorrect')],
            ]);
        }

        $user->password = Hash::make((string) $request->input('password'));
        $user->save();

        return redirect()->route('account.index')->with('success', __('account.password_updated'));
    }

    /** Personal data export — the authenticated user's own account data as JSON. */
    public function export(): JsonResponse
    {
        $user = $this->currentUser();

        $reminders = UserNotificationReminder::query()
            ->where('user_id', $user->user_id)
            ->orderBy('remind_at')
            ->get(['title', 'body', 'remind_at', 'recurrence', 'channels']);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'full_name' => User::fullNameFromParts(
                    $user->first_name ?? '',
                    $user->second_name ?? '',
                    $user->third_name ?? ''
                ),
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'national_id' => $user->national_id,
                'job' => $user->job,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'application_status' => $user->application_status,
                'registration_date' => $user->created_at?->toIso8601String(),
            ],
            'roles' => $user->roles()->get()->map(fn ($role) => [
                'role' => $role->role_name,
                'course_id' => $role->pivot->course_id ?? null,
            ])->values(),
            'notification_reminders' => $reminders,
        ];

        $filename = 'account-export-'.$user->user_id.'-'.now()->format('Ymd').'.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(500, 'Authenticated user is not a valid User instance.');
        }

        return $user;
    }
}
