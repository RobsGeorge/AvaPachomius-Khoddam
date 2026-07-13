<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\ServiceApplication;
use App\Models\ServiceApplicationForm;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ServiceApplicationService
{
    public function __construct(
        private ServiceRoleAssignmentService $assignments,
    ) {}

    public function ensureForm(ChurchService $service): ServiceApplicationForm
    {
        $form = ServiceApplicationForm::query()->where('service_id', $service->service_id)->first();
        if ($form) {
            return $form;
        }

        $memberRole = $this->assignments->memberRoleFor($service);

        return ServiceApplicationForm::create([
            'service_id' => $service->service_id,
            'title' => __('service.application_default_title', ['service' => $service->localizedTitle()]),
            'instructions' => null,
            'default_role_id' => $memberRole->role_id,
            'is_enabled' => true,
        ]);
    }

    public function submit(User $user, ChurchService $service, array $snapshot = []): ServiceApplication
    {
        $form = $this->ensureForm($service);
        if (! $form->is_enabled) {
            throw ValidationException::withMessages([
                'form' => __('service.application_disabled'),
            ]);
        }

        $existing = ServiceApplication::query()
            ->where('user_id', $user->user_id)
            ->where('service_id', $service->service_id)
            ->where('status', ServiceApplication::STATUS_PENDING)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'application' => __('service.application_already_pending'),
            ]);
        }

        return ServiceApplication::create([
            'user_id' => $user->user_id,
            'service_id' => $service->service_id,
            'form_id' => $form->service_application_form_id,
            'status' => ServiceApplication::STATUS_PENDING,
            'snapshot' => $snapshot,
            'submitted_at' => now(),
        ]);
    }

    public function approve(ServiceApplication $application, User $reviewer, ?string $note = null): void
    {
        if ($application->status !== ServiceApplication::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'application' => __('service.application_not_pending'),
            ]);
        }

        $service = $application->service;
        $form = $application->form;
        $role = $form?->defaultRole ?: $this->assignments->memberRoleFor($service);

        $alreadyMember = $this->assignments->userBelongsToService($application->user, $service);
        $this->assignments->assign(
            $application->user,
            $service,
            $role,
            asPrimary: ! $alreadyMember && ! $application->user->userServiceRoles()->exists(),
            allowCrossService: $alreadyMember || $application->user->userServiceRoles()->exists(),
        );

        $application->update([
            'status' => ServiceApplication::STATUS_APPROVED,
            'admin_note' => $note,
            'reviewed_by_user_id' => $reviewer->user_id,
            'reviewed_at' => now(),
        ]);
    }

    public function reject(ServiceApplication $application, User $reviewer, ?string $note = null): void
    {
        if ($application->status !== ServiceApplication::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'application' => __('service.application_not_pending'),
            ]);
        }

        $application->update([
            'status' => ServiceApplication::STATUS_REJECTED,
            'admin_note' => $note,
            'reviewed_by_user_id' => $reviewer->user_id,
            'reviewed_at' => now(),
        ]);
    }
}
