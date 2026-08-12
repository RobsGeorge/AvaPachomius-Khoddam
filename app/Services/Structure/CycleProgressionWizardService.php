<?php

namespace App\Services\Structure;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PersonPlacement;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserServiceRole;
use App\Services\AuditLogService;
use App\Services\CoursePermissionResolver;
use App\Services\CourseRoleAssignmentService;
use App\Services\NotificationGeneratorService;
use App\Support\Structure\ProgressionLadder;
use App\Support\Structure\ProgressionPolicy;
use App\Support\Structure\RosterStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * T9b — End-of-Cycle propose → confirm → apply (no silent auto-promote).
 */
class CycleProgressionWizardService
{
    public const ACTION_PROMOTE = 'promote';

    public const ACTION_SKIP = 'skip';

    public const ACTION_INACTIVE = 'mark_inactive';

    public function __construct(
        private StructureAnchorResolver $resolver,
        private CycleProgressionEligibility $eligibility,
        private CourseRoleAssignmentService $courseRoles,
        private NotificationGeneratorService $notifications,
        private CoursePermissionResolver $permissions,
    ) {}

    public function assertWizardAllowed(ChurchService $service): void
    {
        if (! $this->eligibility->serviceSupportsWizard($service)) {
            $policy = $this->resolver->progressionPolicy($service) ?? ProgressionPolicy::CONTINUOUS_OPEN;
            throw ValidationException::withMessages([
                'service' => [__('service.cycle_wizard_not_available', ['policy' => __('service.progression_'.$policy)])],
            ]);
        }
    }

    /**
     * @return array{
     *   policy: string,
     *   edges: list<array{from_course_id: int, to_course_id: int}>,
     *   rows: list<array<string, mixed>>,
     *   counts: array{eligible: int, ready: int, blocked: int}
     * }
     */
    public function propose(ChurchService $service): array
    {
        $this->assertWizardAllowed($service);

        $edges = $this->resolver->ladderEdges($service);
        $enrollments = $this->eligibility->proposeEligibleEnrollments($service)->load(['user', 'course', 'role']);
        $placements = $this->eligibility->proposeEligiblePlacements($service)->load(['person', 'course']);

        $rows = [];
        $ready = 0;
        $blocked = 0;

        foreach ($enrollments as $enrollment) {
            $fromCourseId = (int) $enrollment->course_id;
            $toCourseId = ProgressionLadder::nextCourseId($edges, $fromCourseId);
            $blockReason = null;
            $suggested = self::ACTION_PROMOTE;

            if ($toCourseId === null) {
                $blockReason = 'missing_edge';
                $suggested = self::ACTION_SKIP;
                $blocked++;
            } else {
                $ready++;
            }

            $rows[] = [
                'enrollment_id' => (int) $enrollment->enrollment_id,
                'placement_id' => null,
                'user_id' => (int) $enrollment->user_id,
                'user_name' => $enrollment->user?->displayName() ?: (string) $enrollment->user_id,
                'role_id' => (int) $enrollment->role_id,
                'from_course_id' => $fromCourseId,
                'from_course_title' => $enrollment->course?->localizedTitle() ?? (string) $fromCourseId,
                'to_course_id' => $toCourseId,
                'to_course_title' => $toCourseId
                    ? (Course::find($toCourseId)?->localizedTitle() ?? (string) $toCourseId)
                    : null,
                'suggested_action' => $suggested,
                'block_reason' => $blockReason,
            ];
        }

        foreach ($placements as $placement) {
            $fromCourseId = (int) $placement->course_id;
            $toCourseId = ProgressionLadder::nextCourseId($edges, $fromCourseId);
            $blockReason = null;
            $suggested = self::ACTION_PROMOTE;

            if ($toCourseId === null) {
                $blockReason = 'missing_edge';
                $suggested = self::ACTION_SKIP;
                $blocked++;
            } else {
                $ready++;
            }

            $rows[] = [
                'enrollment_id' => null,
                'placement_id' => (int) $placement->person_placement_id,
                'user_id' => null,
                'user_name' => $placement->person?->display_name ?: (string) $placement->person_id,
                'role_id' => (int) ($placement->intended_role_id ?? 0),
                'from_course_id' => $fromCourseId,
                'from_course_title' => $placement->course?->localizedTitle() ?? (string) $fromCourseId,
                'to_course_id' => $toCourseId,
                'to_course_title' => $toCourseId
                    ? (Course::find($toCourseId)?->localizedTitle() ?? (string) $toCourseId)
                    : null,
                'suggested_action' => $suggested,
                'block_reason' => $blockReason,
            ];
        }

        return [
            'policy' => (string) $this->resolver->progressionPolicy($service),
            'edges' => $edges,
            'rows' => $rows,
            'counts' => [
                'eligible' => count($rows),
                'ready' => $ready,
                'blocked' => $blocked,
            ],
        ];
    }

    /**
     * @param  list<array{enrollment_id?: int|null, placement_id?: int|null, action: string, to_course_id?: int|null}>  $decisions
     * @return array{moved: int, skipped: int, inactivated: int, audit: array<string, mixed>}
     */
    public function apply(ChurchService $service, User $actor, array $decisions): array
    {
        $this->assertWizardAllowed($service);

        $proposal = $this->propose($service);
        $byEnrollmentId = collect($proposal['rows'])->filter(fn ($row) => $row['enrollment_id'] !== null)->keyBy('enrollment_id');
        $byPlacementId = collect($proposal['rows'])->filter(fn ($row) => $row['placement_id'] !== null)->keyBy('placement_id');

        $moved = [];
        $skipped = [];
        $inactivated = [];

        DB::transaction(function () use (
            $service,
            $actor,
            $decisions,
            $byEnrollmentId,
            $byPlacementId,
            &$moved,
            &$skipped,
            &$inactivated,
        ) {
            foreach ($decisions as $decision) {
                $enrollmentId = isset($decision['enrollment_id']) ? (int) $decision['enrollment_id'] : null;
                $placementId = isset($decision['placement_id']) ? (int) $decision['placement_id'] : null;
                $action = (string) ($decision['action'] ?? self::ACTION_SKIP);

                if ($placementId) {
                    $row = $byPlacementId->get($placementId);
                    if (! $row) {
                        continue;
                    }

                    $placement = PersonPlacement::query()->find($placementId);
                    if (! $placement || ! $this->eligibility->placementEligibleForPropose($placement)) {
                        continue;
                    }

                    $this->applyPlacementDecision(
                        $service, $placement, $row, $action, $decision, $placementId, $moved, $skipped, $inactivated
                    );

                    continue;
                }

                if (! $enrollmentId) {
                    continue;
                }

                $row = $byEnrollmentId->get($enrollmentId);
                if (! $row) {
                    continue;
                }

                $enrollment = Enrollment::query()->find($enrollmentId);
                if (! $enrollment || ! $this->eligibility->enrollmentEligibleForPropose($enrollment)) {
                    continue;
                }

                if ($action === self::ACTION_SKIP) {
                    $skipped[] = [
                        'type' => 'enrollment',
                        'enrollment_id' => $enrollmentId,
                        'user_id' => $row['user_id'],
                        'from_course_id' => $row['from_course_id'],
                    ];
                    continue;
                }

                if ($action === self::ACTION_INACTIVE) {
                    $this->markEnrollmentInactive($enrollment, (string) ($decision['note'] ?? ''));
                    $inactivated[] = [
                        'type' => 'enrollment',
                        'enrollment_id' => $enrollmentId,
                        'user_id' => $row['user_id'],
                        'from_course_id' => $row['from_course_id'],
                    ];
                    continue;
                }

                if ($action === self::ACTION_PROMOTE) {
                    $toCourseId = isset($decision['to_course_id']) && (int) $decision['to_course_id'] > 0
                        ? (int) $decision['to_course_id']
                        : ($row['to_course_id'] ? (int) $row['to_course_id'] : null);

                    if ($toCourseId === null) {
                        throw ValidationException::withMessages([
                            "decisions.{$enrollmentId}" => [__('service.cycle_missing_target')],
                        ]);
                    }

                    $this->assertCourseInService($service, $toCourseId);
                    $this->promoteEnrollment($enrollment, $toCourseId);

                    $moved[] = [
                        'type' => 'enrollment',
                        'enrollment_id' => $enrollmentId,
                        'user_id' => $row['user_id'],
                        'from_course_id' => $row['from_course_id'],
                        'to_course_id' => $toCourseId,
                    ];
                }
            }
        });

        $audit = [
            'service_id' => $service->service_id,
            'policy' => $proposal['policy'],
            'actor_user_id' => $actor->user_id,
            'moved' => $moved,
            'skipped' => $skipped,
            'inactivated' => $inactivated,
        ];

        AuditLogService::recordEvent('service.progression.applied', $audit);
        $this->notifyServiceAdmins($service, $actor, $audit);

        // T9c: during a closing school year, auto-mark the service done when none remain eligible.
        app(ChurchCycleSeasonService::class)->maybeMarkServiceDoneAfterApply($service);

        return [
            'moved' => count($moved),
            'skipped' => count($skipped),
            'inactivated' => count($inactivated),
            'audit' => $audit,
        ];
    }

    public function saveLadderEdges(ChurchService $service, array $edgeRows): ChurchService
    {
        $this->assertWizardAllowed($service);

        $ladder = ProgressionLadder::configFromEdgeRows($edgeRows);
        $config = is_array($service->progression_config) ? $service->progression_config : [];
        $config['ladder'] = $ladder['ladder'];
        $service->progression_config = $config;
        $service->save();

        return $service->fresh();
    }

    private function promoteEnrollment(Enrollment $enrollment, int $toCourseId): void
    {
        $user = User::query()->findOrFail($enrollment->user_id);
        $roleId = (int) $enrollment->role_id;
        $oldUcrId = $enrollment->user_course_role_id;

        $this->courseRoles->assignOrUpdate($user, $toCourseId, $roleId, notify: false);

        if ($oldUcrId) {
            $old = UserCourseRole::query()->find($oldUcrId);
            if ($old && (int) $old->course_id !== $toCourseId) {
                $old->delete();
            }
        } else {
            // No UCR link: archive the enrollment row directly.
            $enrollment->status = RosterStatus::ARCHIVED;
            $enrollment->status_changed_at = now();
            $enrollment->save();
        }
    }

    private function markEnrollmentInactive(Enrollment $enrollment, string $note): void
    {
        $enrollment->loadMissing('course');

        $enrollment->status = RosterStatus::INACTIVE;
        $enrollment->status_note = $note !== '' ? mb_substr($note, 0, 500) : __('service.cycle_marked_inactive');
        $enrollment->status_changed_at = now();
        $enrollment->save();

        $serviceId = $enrollment->course?->service_id;
        if (Schema::hasColumn('user_service_role', 'roster_status') && $serviceId) {
            UserServiceRole::query()
                ->where('user_id', $enrollment->user_id)
                ->where('service_id', $serviceId)
                ->update([
                    'roster_status' => RosterStatus::INACTIVE,
                    'status_note' => $enrollment->status_note,
                    'status_changed_at' => now(),
                ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $decision
     * @param  list<array<string, mixed>>  $moved
     * @param  list<array<string, mixed>>  $skipped
     * @param  list<array<string, mixed>>  $inactivated
     */
    private function applyPlacementDecision(
        ChurchService $service,
        PersonPlacement $placement,
        array $row,
        string $action,
        array $decision,
        int $placementId,
        array &$moved,
        array &$skipped,
        array &$inactivated,
    ): void {
        if ($action === self::ACTION_SKIP) {
            $skipped[] = [
                'type' => 'placement',
                'placement_id' => $placementId,
                'user_name' => $row['user_name'],
                'from_course_id' => $row['from_course_id'],
            ];

            return;
        }

        if ($action === self::ACTION_INACTIVE) {
            $this->markPlacementInactive($placement, (string) ($decision['note'] ?? ''));
            $inactivated[] = [
                'type' => 'placement',
                'placement_id' => $placementId,
                'user_name' => $row['user_name'],
                'from_course_id' => $row['from_course_id'],
            ];

            return;
        }

        if ($action === self::ACTION_PROMOTE) {
            $toCourseId = isset($decision['to_course_id']) && (int) $decision['to_course_id'] > 0
                ? (int) $decision['to_course_id']
                : ($row['to_course_id'] ? (int) $row['to_course_id'] : null);

            if ($toCourseId === null) {
                throw ValidationException::withMessages([
                    "decisions.placement.{$placementId}" => [__('service.cycle_missing_target')],
                ]);
            }

            $this->assertCourseInService($service, $toCourseId);
            $this->promotePlacement($placement, $toCourseId);

            $moved[] = [
                'type' => 'placement',
                'placement_id' => $placementId,
                'user_name' => $row['user_name'],
                'from_course_id' => $row['from_course_id'],
                'to_course_id' => $toCourseId,
            ];
        }
    }

    private function promotePlacement(PersonPlacement $placement, int $toCourseId): void
    {
        $placement->course_id = $toCourseId;
        $placement->save();
    }

    private function markPlacementInactive(PersonPlacement $placement, string $note): void
    {
        $placement->roster_status = RosterStatus::INACTIVE;
        $placement->status_note = $note !== '' ? mb_substr($note, 0, 500) : __('service.cycle_marked_inactive');
        $placement->save();
    }

    private function assertCourseInService(ChurchService $service, int $courseId): void
    {
        $ok = Course::query()
            ->where('course_id', $courseId)
            ->where('service_id', $service->service_id)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'to_course_id' => [__('service.cycle_target_not_in_service')],
            ]);
        }
    }

    /** @param array<string, mixed> $audit */
    private function notifyServiceAdmins(ChurchService $service, User $actor, array $audit): void
    {
        $recipients = UserServiceRole::query()
            ->where('service_id', $service->service_id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('user_id')
            ->filter(function (User $user) use ($service, $actor) {
                if ((int) $user->user_id === (int) $actor->user_id) {
                    return true;
                }

                return $this->permissions->canInService($user, 'service.progression.run', $service)
                    || $this->permissions->canInService($user, 'service.manage', $service);
            });

        $runKey = sha1(json_encode($audit) ?: uniqid('cycle', true));
        $title = __('service.cycle_applied_notification_title', [
            'service' => $service->localizedTitle(),
        ]);
        $body = __('service.cycle_applied_notification_body', [
            'moved' => count($audit['moved'] ?? []),
            'skipped' => count($audit['skipped'] ?? []),
            'inactivated' => count($audit['inactivated'] ?? []),
        ]);

        foreach ($recipients as $user) {
            $this->notifications->createOrUpdate(
                $user,
                'service_progression_applied',
                $title,
                $body,
                route('admin.services.cycle.show', $service),
                ChurchService::class,
                (int) $service->service_id,
                metadata: ['service_id' => $service->service_id],
                dedupeKey: "service_progression_applied:{$runKey}:{$user->user_id}",
            );
        }
    }
}
