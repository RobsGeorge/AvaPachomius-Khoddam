<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotificationReminder;
use App\Services\NotificationPreferenceService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function __construct(
        private NotificationPreferenceService $preferences,
        private WhatsAppNotificationService $whatsapp,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $types = $this->preferences->typesForUser($user);
        $prefs = $this->preferences->preferencesForUser($user)->keyBy('type');

        return response()->json([
            'data' => [
                'communication_locale' => $user->communication_locale,
                'whatsapp_configured' => $this->whatsapp->isConfigured(),
                'types' => collect($types)->map(function ($definition, $type) use ($prefs) {
                    $pref = $prefs->get($type);

                    return [
                        'type' => $type,
                        'label' => $definition['label'] ?? $type,
                        'portal_enabled' => (bool) ($pref?->portal_enabled ?? true),
                        'email_enabled' => (bool) ($pref?->email_enabled ?? false),
                        'whatsapp_enabled' => (bool) ($pref?->whatsapp_enabled ?? false),
                    ];
                })->values(),
                'reminders' => UserNotificationReminder::query()
                    ->where('user_id', $user->user_id)
                    ->orderBy('remind_at')
                    ->get()
                    ->map(fn (UserNotificationReminder $r) => [
                        'id' => $r->id ?? $r->getKey(),
                        'title' => $r->title,
                        'body' => $r->body,
                        'remind_at' => $r->remind_at?->toIso8601String(),
                        'recurrence' => $r->recurrence,
                        'channels' => $r->channels,
                    ])->values(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'communication_locale' => ['nullable', 'in:ar,en'],
            'preferences' => ['nullable', 'array'],
        ]);

        if (\App\Services\EmailTemplateCatalog::userCommunicationLocaleColumnReady()) {
            $user->communication_locale = $validated['communication_locale'] ?? null;
            $user->save();
        }

        $this->preferences->save($user, $request->input('preferences', []));

        return $this->show($request);
    }
}
