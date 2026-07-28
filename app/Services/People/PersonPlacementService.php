<?php

namespace App\Services\People;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\Person;
use App\Models\PersonPlacement;
use App\Models\Role;
use App\Services\AuditLogService;
use App\Support\People\PlacementMode;
use App\Support\Structure\RosterStatus;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PersonPlacementService
{
    public function place(
        Person $person,
        ChurchService $service,
        ?Course $course = null,
        ?Role $intendedRole = null,
        string $placementMode = PlacementMode::INFO_ONLY,
        string $rosterStatus = RosterStatus::ACTIVE,
        ?int $serviceUnitId = null,
        ?string $statusNote = null,
    ): PersonPlacement {
        if (! Schema::hasTable('person_placements')) {
            throw new \RuntimeException('person_placements schema is not migrated.');
        }

        if (! PlacementMode::isValid($placementMode)) {
            throw new InvalidArgumentException('Invalid placement_mode.');
        }

        if (! RosterStatus::isValid($rosterStatus)) {
            throw new InvalidArgumentException('Invalid roster_status.');
        }

        if ($course && (int) $course->service_id !== (int) $service->service_id) {
            throw new InvalidArgumentException('Course does not belong to service.');
        }

        if ($intendedRole) {
            $this->assertRoleInScope($intendedRole, $service, $course);
        }

        $churchId = (int) ($person->church_id ?: $service->church_id ?: TenantContext::id());

        return DB::transaction(function () use (
            $person, $service, $course, $intendedRole, $placementMode,
            $rosterStatus, $serviceUnitId, $statusNote, $churchId
        ) {
            $query = PersonPlacement::withoutTenancy()
                ->where('person_id', $person->person_id)
                ->where('service_id', $service->service_id);

            if ($course) {
                $query->where('course_id', $course->course_id);
            } else {
                $query->whereNull('course_id');
            }

            $placement = $query->first();

            $attrs = [
                'church_id' => $churchId,
                'person_id' => $person->person_id,
                'service_id' => $service->service_id,
                'course_id' => $course?->course_id,
                'service_unit_id' => $serviceUnitId,
                'roster_status' => $rosterStatus,
                'intended_role_id' => $intendedRole?->role_id,
                'placement_mode' => $placementMode,
                'status_note' => $statusNote,
            ];

            if ($placement) {
                $placement->fill($attrs)->save();
            } else {
                $placement = PersonPlacement::withoutTenancy()->create($attrs);
            }

            AuditLogService::recordEvent('people.placement_upserted', [
                'person_placement_id' => $placement->person_placement_id,
                'person_id' => $person->person_id,
                'service_id' => $service->service_id,
                'course_id' => $course?->course_id,
                'placement_mode' => $placementMode,
            ]);

            return $placement->fresh();
        });
    }

    public function retire(PersonPlacement $placement, ?string $note = null): PersonPlacement
    {
        $placement->forceFill([
            'roster_status' => RosterStatus::LEFT,
            'status_note' => $note ?? $placement->status_note,
        ])->save();

        AuditLogService::recordEvent('people.placement_retired', [
            'person_placement_id' => $placement->person_placement_id,
            'person_id' => $placement->person_id,
        ]);

        return $placement->fresh();
    }

    private function assertRoleInScope(Role $role, ChurchService $service, ?Course $course): void
    {
        if ($course && $role->course_id && (int) $role->course_id !== (int) $course->course_id) {
            throw new InvalidArgumentException('Role is not scoped to the course.');
        }

        if ($role->service_id && (int) $role->service_id !== (int) $service->service_id) {
            throw new InvalidArgumentException('Role is not scoped to the service.');
        }
    }
}
