<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class NotificationPreferenceService
{
    public function typesForUser(User $user): array
    {
        $roles = $this->userRoles($user);
        $types = [];

        foreach (config('notifications.types', []) as $type => $definition) {
            $audience = $definition['audience'] ?? [];
            if (array_intersect($roles, $audience) !== []) {
                $types[$type] = $definition;
            }
        }

        return $types;
    }

    public function ensureDefaults(User $user): void
    {
        foreach ($this->typesForUser($user) as $type => $definition) {
            UserNotificationPreference::query()->firstOrCreate(
                ['user_id' => $user->user_id, 'type' => $type],
                [
                    'portal_enabled' => $definition['defaults']['portal_enabled'] ?? true,
                    'email_enabled' => $definition['defaults']['email_enabled'] ?? false,
                    'whatsapp_enabled' => $definition['defaults']['whatsapp_enabled'] ?? false,
                    'config' => $definition['defaults']['config'] ?? [],
                ]
            );
        }
    }

    public function preferencesForUser(User $user): Collection
    {
        $this->ensureDefaults($user);

        return UserNotificationPreference::query()
            ->where('user_id', $user->user_id)
            ->whereIn('type', array_keys($this->typesForUser($user)))
            ->orderBy('type')
            ->get();
    }

    public function preferenceFor(User $user, string $type): UserNotificationPreference
    {
        $this->ensureDefaults($user);

        $existing = UserNotificationPreference::query()
            ->where('user_id', $user->user_id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Explicit notify paths (e.g. course_application.review without role.manage)
        // may target users outside the type's default audience — still seed defaults.
        $definition = config("notifications.types.{$type}", []);

        return UserNotificationPreference::query()->create([
            'user_id' => $user->user_id,
            'type' => $type,
            'portal_enabled' => $definition['defaults']['portal_enabled'] ?? true,
            'email_enabled' => $definition['defaults']['email_enabled'] ?? false,
            'whatsapp_enabled' => $definition['defaults']['whatsapp_enabled'] ?? false,
            'config' => $definition['defaults']['config'] ?? [],
        ]);
    }

    public function configValue(User $user, string $type, string $key, mixed $default = null): mixed
    {
        $pref = $this->preferenceFor($user, $type);

        return $pref->config[$key] ?? config("notifications.types.{$type}.defaults.config.{$key}", $default);
    }

    /** @param array<string, array{portal_enabled?: bool, email_enabled?: bool, whatsapp_enabled?: bool, config?: array}> $input */
    public function save(User $user, array $input): void
    {
        $allowedTypes = array_keys($this->typesForUser($user));

        foreach ($input as $type => $payload) {
            if (! in_array($type, $allowedTypes, true)) {
                continue;
            }

            $portal = (bool) ($payload['portal_enabled'] ?? false);
            $email = (bool) ($payload['email_enabled'] ?? false);
            $whatsapp = (bool) ($payload['whatsapp_enabled'] ?? false);

            if ($this->isMandatory($type) && ! $portal && ! $email && ! $whatsapp) {
                throw ValidationException::withMessages([
                    "preferences.{$type}.channels" => __('notifications.mandatory_channel_required'),
                ]);
            }

            $pref = $this->preferenceFor($user, $type);
            $pref->update([
                'portal_enabled' => $portal,
                'email_enabled' => $email,
                'whatsapp_enabled' => $whatsapp,
                'config' => array_merge($pref->config ?? [], $payload['config'] ?? []),
            ]);
        }
    }

    public function ensureMandatoryChannels(User $user, string $type): UserNotificationPreference
    {
        $pref = $this->preferenceFor($user, $type);

        if ($this->isMandatory($type) && ! $pref->portal_enabled && ! $pref->email_enabled && ! $pref->whatsapp_enabled) {
            $pref->update(['portal_enabled' => true]);
            $pref->refresh();
        }

        return $pref;
    }

    public function isMandatory(string $type): bool
    {
        return (bool) (config("notifications.types.{$type}.mandatory") ?? false);
    }

    /** @return list<string> */
    private function userRoles(User $user): array
    {
        $roles = [];

        if ($user->is_superadmin || $user->isAdmin() || $user->canAccessAdminCourseApplications()) {
            $roles[] = 'admin';
        }

        if ($user->isEventAdmin() && ! in_array('admin', $roles, true)) {
            $roles[] = 'admin';
        }

        if ($user->isInstructorOrAdmin()) {
            $roles[] = 'instructor';
        }

        if ($user->isStudent()) {
            $roles[] = 'student';
        }

        if ($roles === []) {
            $roles[] = 'student';
        }

        return array_values(array_unique($roles));
    }
}
