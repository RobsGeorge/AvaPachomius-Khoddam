<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationReminder;
use App\Services\NotificationPreferenceService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function destroyReminder(UserNotificationReminder $reminder)
    {
        abort_unless($reminder->user_id === Auth::user()->user_id, 403);
        $reminder->delete();

        return back()->with('success', __('notifications.reminder_deleted'));
    }
}
