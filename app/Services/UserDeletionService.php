<?php

namespace App\Services;

use App\Mail\AccountDeletedMail;
use App\Models\ActivityLog;
use App\Models\Church;
use App\Models\ChurchService;
use App\Models\ChurchUser;
use App\Models\EventAdmin;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserNotification;
use App\Models\UserServiceRole;
use App\Models\UserSystemRole;
use App\Support\SuperadminWorkspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UserDeletionService
{
    public function __construct(
        private WhatsAppNotificationService $whatsapp,
    ) {}

    /**
     * @return array{churches: Collection<int, Church>, services: Collection<int, ChurchService>}
     */
    public function filterOptions(): array
    {
        $requiresChurch = SuperadminWorkspace::requiresExplicitChurchScope();

        $churches = Schema::hasTable('church')
            ? Church::query()->orderBy('name')->get(['church_id', 'name', 'short_name', 'slug'])
            : collect();

        $services = ChurchService::tableReady()
            ? ChurchService::query()
                // Platform-wide picker for superadmin user deletion (cross-church).
                ->when($requiresChurch, fn ($q) => $q->withoutTenancy())
                ->when(Schema::hasColumn('service', 'church_id'), fn ($q) => $q->with('church'))
                ->orderBy('title')
                ->get()
            : collect();

        return compact('churches', 'services');
    }

    public function search(
        ?string $name,
        ?int $churchId,
        ?int $serviceId,
        bool $includeDeleted,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = User::query()
            ->with([
                'churches',
                'userServiceRoles' => function ($q) {
                    // Cross-church memberships for the platform deletion console.
                    $q->withoutTenancy()->with(['service' => fn ($s) => $s->withoutTenancy()]);
                },
            ])
            ->orderBy('first_name')
            ->orderBy('second_name')
            ->orderBy('user_id');

        if ($includeDeleted) {
            $query->withTrashed();
        }

        $name = trim((string) $name);
        if ($name !== '') {
            $like = '%'.$name.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('first_name', 'like', $like)
                    ->orWhere('second_name', 'like', $like)
                    ->orWhere('third_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like);
            });
        }

        if ($churchId && Schema::hasTable('church_user')) {
            $query->whereHas('churchMemberships', fn ($q) => $q->where('church_id', $churchId));
        }

        if ($serviceId && Schema::hasTable('user_service_role')) {
            $query->whereHas('userServiceRoles', function ($q) use ($serviceId) {
                // Cross-church service membership filter for the platform console.
                $q->withoutTenancy()->where('service_id', $serviceId);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findManaged(int $userId): User
    {
        return User::withTrashed()
            ->with([
                'churches',
                'userServiceRoles' => function ($q) {
                    // Cross-church memberships for the platform deletion console.
                    $q->withoutTenancy()->with(['service' => fn ($s) => $s->withoutTenancy()]);
                },
            ])
            ->findOrFail($userId);
    }

    /**
     * @return array{email: bool, whatsapp: bool, email_error?: string, whatsapp_error?: string}
     */
    public function softDelete(User $actor, User $target, bool $notifyEmail, bool $notifyWhatsapp): array
    {
        $this->assertCanDelete($actor, $target);

        if ($target->trashed()) {
            throw ValidationException::withMessages([
                'user' => __('user_deletion.already_deleted'),
            ]);
        }

        $notices = $this->notify($target, $notifyEmail, $notifyWhatsapp, permanent: false);

        ForceLogoutService::logoutUsers([$target->user_id]);
        $target->delete();

        AuditLogService::recordEvent('superadmin.users.soft-delete', [
            'target_user_id' => $target->user_id,
            'target_email' => $target->email,
            'target_name' => $target->displayName(),
            'notify_email' => $notifyEmail,
            'notify_whatsapp' => $notifyWhatsapp,
        ]);

        return $notices;
    }

    /**
     * @return array{email: bool, whatsapp: bool, email_error?: string, whatsapp_error?: string}
     */
    public function hardDelete(
        User $actor,
        User $target,
        string $confirmation,
        bool $acknowledged,
        bool $notifyEmail,
        bool $notifyWhatsapp,
    ): array {
        $this->assertCanDelete($actor, $target);

        if (! $acknowledged) {
            throw ValidationException::withMessages([
                'acknowledge' => __('user_deletion.acknowledge_required'),
            ]);
        }

        $expected = mb_strtolower(trim((string) $target->email));
        if ($expected === '' || mb_strtolower(trim($confirmation)) !== $expected) {
            throw ValidationException::withMessages([
                'confirmation' => __('user_deletion.confirmation_mismatch'),
            ]);
        }

        $permanent = true;
        $notices = $target->trashed()
            ? ['email' => false, 'whatsapp' => false]
            : $this->notify($target, $notifyEmail, $notifyWhatsapp, $permanent);

        ForceLogoutService::logoutUsers([$target->user_id]);

        $snapshot = [
            'target_user_id' => $target->user_id,
            'target_email' => $target->email,
            'target_name' => $target->displayName(),
            'notify_email' => $notifyEmail,
            'notify_whatsapp' => $notifyWhatsapp,
            'was_trashed' => $target->trashed(),
        ];

        try {
            DB::transaction(function () use ($target) {
                $this->purgeOwnedRecords($target);
                $target->forceDelete();
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'user' => __('user_deletion.hard_delete_blocked'),
            ]);
        }

        AuditLogService::recordEvent('superadmin.users.hard-delete', $snapshot);

        return $notices;
    }

    private function assertCanDelete(User $actor, User $target): void
    {
        if ((int) $actor->user_id === (int) $target->user_id) {
            throw ValidationException::withMessages([
                'user' => __('user_deletion.cannot_delete_self'),
            ]);
        }

        if ($target->is_superadmin && $this->remainingSuperadminCount($target) < 1) {
            throw ValidationException::withMessages([
                'user' => __('user_deletion.cannot_delete_last_superadmin'),
            ]);
        }
    }

    private function remainingSuperadminCount(User $excluding): int
    {
        return User::query()
            ->where('is_superadmin', true)
            ->where('user_id', '!=', $excluding->user_id)
            ->count();
    }

    /**
     * @return array{email: bool, whatsapp: bool, email_error?: string, whatsapp_error?: string}
     */
    private function notify(User $target, bool $notifyEmail, bool $notifyWhatsapp, bool $permanent): array
    {
        $result = ['email' => false, 'whatsapp' => false];

        if ($notifyEmail && ! filled($target->email)) {
            throw ValidationException::withMessages([
                'notify_email' => __('user_deletion.missing_email'),
            ]);
        }

        if ($notifyWhatsapp && ! filled($target->mobile_number)) {
            throw ValidationException::withMessages([
                'notify_whatsapp' => __('user_deletion.missing_mobile'),
            ]);
        }

        $previousLocale = app()->getLocale();
        $locale = $this->localeFor($target);

        try {
            app()->setLocale($locale);

            if ($notifyEmail) {
                Mail::to($target->email)->send(new AccountDeletedMail($target, $permanent));
                $result['email'] = true;
            }

            if ($notifyWhatsapp) {
                $body = __('user_deletion.whatsapp_body', [
                    'name' => $target->displayName(),
                    'mode' => $permanent
                        ? __('user_deletion.mode_hard')
                        : __('user_deletion.mode_soft'),
                ]);
                $sent = $this->whatsapp->sendAccountNotice(
                    $target,
                    __('user_deletion.whatsapp_subject'),
                    $body,
                );
                $result['whatsapp'] = (bool) ($sent['ok'] ?? false);
                if (! $result['whatsapp']) {
                    $result['whatsapp_error'] = $sent['error'] ?? 'failed';
                }
            }
        } finally {
            app()->setLocale($previousLocale);
        }

        return $result;
    }

    private function localeFor(User $user): string
    {
        foreach ([$user->communication_locale ?? null, $user->ui_locale ?? null] as $candidate) {
            if (is_string($candidate) && in_array($candidate, ['ar', 'en'], true)) {
                return $candidate;
            }
        }

        return 'ar';
    }

    private function purgeOwnedRecords(User $target): void
    {
        $userId = (int) $target->user_id;

        if (Schema::hasTable('church_user')) {
            ChurchUser::query()->where('user_id', $userId)->delete();
        }

        if (Schema::hasTable('user_service_role')) {
            // Platform deletion removes every service membership for this login.
            UserServiceRole::withoutTenancy()->where('user_id', $userId)->delete();
        }

        if (Schema::hasTable('user_course_role')) {
            // Platform deletion removes every course assignment for this login.
            UserCourseRole::withoutTenancy()->where('user_id', $userId)->delete();
        }

        if (Schema::hasTable('user_system_role')) {
            UserSystemRole::query()->where('user_id', $userId)->delete();
        }

        if (Schema::hasTable('event_admins')) {
            EventAdmin::query()->where('user_id', $userId)->delete();
        }

        if (Schema::hasTable('otp_code')) {
            OtpCode::query()->where('user_id', $userId)->delete();
        }

        if (Schema::hasTable('user_notifications')) {
            UserNotification::query()->where('user_id', $userId)->delete();
        }

        if (method_exists($target, 'tokens')) {
            $target->tokens()->delete();
        }

        if (Schema::hasTable('activity_logs')) {
            // Preserve audit rows after the login is removed (nullOnDelete).
            ActivityLog::withoutTenancy()->where('user_id', $userId)->update(['user_id' => null]);
        }
    }
}
