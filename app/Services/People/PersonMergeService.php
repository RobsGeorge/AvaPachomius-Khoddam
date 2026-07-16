<?php

namespace App\Services\People;

use App\Models\Attendance;
use App\Models\FamilyMember;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merge duplicate person into survivor: re-point FKs, soft-retire duplicate, audit.
 * Enrollment/attendance stay on user_id; users are re-pointed to the survivor person.
 */
class PersonMergeService
{
    /**
     * @return array{
     *     survivor_id: int,
     *     duplicate_id: int,
     *     users_repointed: int,
     *     family_members_repointed: int,
     *     relationships_repointed: int,
     *     enrollments_intact: int,
     *     attendance_intact: int
     * }
     */
    public function merge(Person $survivor, Person $duplicate, ?User $actor = null): array
    {
        if ($survivor->person_id === $duplicate->person_id) {
            throw new InvalidArgumentException('Survivor and duplicate must be different people.');
        }

        if ($survivor->isRetired()) {
            throw new InvalidArgumentException('Survivor person is retired.');
        }

        if ($duplicate->isRetired()) {
            throw new InvalidArgumentException('Duplicate person is already retired.');
        }

        return DB::transaction(function () use ($survivor, $duplicate, $actor) {
            $survivorId = (int) $survivor->person_id;
            $duplicateId = (int) $duplicate->person_id;

            $userIds = User::query()
                ->where('person_id', $duplicateId)
                ->pluck('user_id')
                ->all();

            $enrollmentsIntact = $userIds === []
                ? 0
                : UserCourseRole::query()->whereIn('user_id', $userIds)->count();

            $attendanceIntact = $userIds === []
                ? 0
                : Attendance::query()->whereIn('user_id', $userIds)->count();

            $usersRepointed = User::query()
                ->where('person_id', $duplicateId)
                ->update(['person_id' => $survivorId]);

            $familyRepointed = 0;
            foreach (FamilyMember::query()->where('person_id', $duplicateId)->get() as $member) {
                $exists = FamilyMember::query()
                    ->where('family_id', $member->family_id)
                    ->where('person_id', $survivorId)
                    ->exists();

                if ($exists) {
                    $member->delete();
                } else {
                    $member->update(['person_id' => $survivorId]);
                    $familyRepointed++;
                }
            }

            $relationshipsRepointed = 0;
            foreach (Relationship::withoutTenancy()->where('person_id', $duplicateId)->get() as $rel) {
                $exists = Relationship::withoutTenancy()
                    ->where('person_id', $survivorId)
                    ->where('related_person_id', $rel->related_person_id)
                    ->where('type', $rel->type)
                    ->exists();

                if ($exists) {
                    $rel->delete();
                } else {
                    $rel->update(['person_id' => $survivorId]);
                    $relationshipsRepointed++;
                }
            }

            foreach (Relationship::withoutTenancy()->where('related_person_id', $duplicateId)->get() as $rel) {
                $exists = Relationship::withoutTenancy()
                    ->where('person_id', $rel->person_id)
                    ->where('related_person_id', $survivorId)
                    ->where('type', $rel->type)
                    ->exists();

                if ($exists) {
                    $rel->delete();
                } else {
                    $rel->update(['related_person_id' => $survivorId]);
                    $relationshipsRepointed++;
                }
            }

            $duplicate->forceFill([
                'retired_at' => now(),
                'merged_into_person_id' => $survivorId,
            ])->save();

            $summary = [
                'survivor_id' => $survivorId,
                'duplicate_id' => $duplicateId,
                'users_repointed' => $usersRepointed,
                'family_members_repointed' => $familyRepointed,
                'relationships_repointed' => $relationshipsRepointed,
                'enrollments_intact' => $enrollmentsIntact,
                'attendance_intact' => $attendanceIntact,
                'actor_user_id' => $actor?->user_id,
            ];

            AuditLogService::recordEvent('people.merge', $summary);

            return $summary;
        });
    }
}
