<?php

namespace App\Services\Structure;

use App\Models\Church;
use App\Models\ChurchSchoolYear;
use App\Models\ChurchService;
use App\Models\User;
use App\Models\UserServiceRole;
use App\Services\AuditLogService;
use App\Services\CoursePermissionResolver;
use App\Services\NotificationGeneratorService;
use App\Support\Structure\CycleDashboardStatus;
use App\Support\Structure\ProgressionPolicy;
use App\Support\Structure\SchoolYearStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * T9c — church school-year season + Cycle Dashboard orchestration.
 * Never applies progression church-wide; only signals + per-service wizard links.
 */
class ChurchCycleSeasonService
{
    public function __construct(
        private StructureAnchorResolver $resolver,
        private CycleProgressionWizardService $wizard,
        private CycleProgressionEligibility $eligibility,
        private NotificationGeneratorService $notifications,
        private CoursePermissionResolver $permissions,
    ) {}

    public function currentOpenYear(?Church $church = null): ?ChurchSchoolYear
    {
        if (! Schema::hasTable('church_school_year')) {
            return null;
        }

        $query = ChurchSchoolYear::query()
            ->whereIn('status', [SchoolYearStatus::ACTIVE, SchoolYearStatus::CLOSING])
            ->orderByDesc('starts_on');

        if ($church) {
            $query->where('church_id', $church->church_id);
        }

        return $query->first();
    }

    public function latestYear(?Church $church = null): ?ChurchSchoolYear
    {
        if (! Schema::hasTable('church_school_year')) {
            return null;
        }

        $query = ChurchSchoolYear::query()->orderByDesc('starts_on')->orderByDesc('church_school_year_id');
        if ($church) {
            $query->where('church_id', $church->church_id);
        }

        return $query->first();
    }

    /**
     * @return array{
     *   year: ?ChurchSchoolYear,
     *   rows: list<array<string, mixed>>,
     *   summary: array{ready: int, blocked: int, done: int, skipped: int, course_close: int}
     * }
     */
    public function dashboard(?Church $church = null): array
    {
        $year = $this->currentOpenYear($church) ?? $this->latestYear($church);
        $services = ChurchService::query()->orderBy('title')->get();
        $rows = [];
        $summary = [
            'ready' => 0,
            'blocked' => 0,
            'done' => 0,
            'skipped' => 0,
            'course_close' => 0,
        ];

        foreach ($services as $service) {
            $row = $this->serviceRow($service, $year);
            $rows[] = $row;
            $key = match ($row['status']) {
                CycleDashboardStatus::READY => 'ready',
                CycleDashboardStatus::BLOCKED => 'blocked',
                CycleDashboardStatus::DONE => 'done',
                CycleDashboardStatus::COURSE_CLOSE => 'course_close',
                default => 'skipped',
            };
            $summary[$key]++;
        }

        return [
            'year' => $year,
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceRow(ChurchService $service, ?ChurchSchoolYear $year): array
    {
        $policy = (string) ($this->resolver->progressionPolicy($service) ?? ProgressionPolicy::CONTINUOUS_OPEN);
        $wizardUrl = route('admin.services.cycle.show', $service);
        $base = [
            'service_id' => (int) $service->service_id,
            'title' => $service->localizedTitle(),
            'policy' => $policy,
            'wizard_url' => $wizardUrl,
            'counts' => null,
            'block_reason' => null,
        ];

        if ($policy === ProgressionPolicy::COURSE_CLOSE_ONLY) {
            return array_merge($base, [
                'status' => CycleDashboardStatus::COURSE_CLOSE,
                'block_reason' => 'course_close_only',
            ]);
        }

        if (! ProgressionPolicy::usesEndOfCycleWizard($policy)) {
            return array_merge($base, [
                'status' => CycleDashboardStatus::SKIPPED,
                'block_reason' => 'continuous_open',
            ]);
        }

        if ($year && $year->hasCompletedService((int) $service->service_id)) {
            return array_merge($base, [
                'status' => CycleDashboardStatus::DONE,
            ]);
        }

        $edges = $this->resolver->ladderEdges($service);
        if ($edges === []) {
            return array_merge($base, [
                'status' => CycleDashboardStatus::BLOCKED,
                'block_reason' => 'missing_ladder',
                'counts' => ['eligible' => 0, 'ready' => 0, 'blocked' => 0],
            ]);
        }

        try {
            $proposal = $this->wizard->propose($service);
            $counts = $proposal['counts'];
        } catch (\Throwable) {
            return array_merge($base, [
                'status' => CycleDashboardStatus::BLOCKED,
                'block_reason' => 'wizard_unavailable',
            ]);
        }

        if (($counts['blocked'] ?? 0) > 0) {
            return array_merge($base, [
                'status' => CycleDashboardStatus::BLOCKED,
                'block_reason' => 'missing_edge',
                'counts' => $counts,
            ]);
        }

        return array_merge($base, [
            'status' => CycleDashboardStatus::READY,
            'counts' => $counts,
        ]);
    }

    public function createYear(Church $church, array $data, User $actor): ChurchSchoolYear
    {
        $label = trim((string) ($data['label'] ?? ''));
        $startsOn = $data['starts_on'] ?? null;
        $endsOn = $data['ends_on'] ?? null;
        $status = (string) ($data['status'] ?? SchoolYearStatus::ACTIVE);

        if ($label === '' || ! $startsOn || ! $endsOn) {
            throw ValidationException::withMessages([
                'label' => [__('church_cycle.validation_required')],
            ]);
        }

        if (! SchoolYearStatus::isValid($status) || $status === SchoolYearStatus::CLOSED) {
            throw ValidationException::withMessages([
                'status' => [__('church_cycle.invalid_status')],
            ]);
        }

        if (SchoolYearStatus::isOpenSeason($status) && $this->currentOpenYear($church)) {
            throw ValidationException::withMessages([
                'status' => [__('church_cycle.open_year_exists')],
            ]);
        }

        $year = new ChurchSchoolYear([
            'label' => $label,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $status,
            'completed_service_ids' => [],
        ]);
        $year->church_id = $church->church_id;
        $year->save();

        AuditLogService::recordEvent('church.cycle.year_created', [
            'church_school_year_id' => $year->church_school_year_id,
            'label' => $year->label,
            'status' => $year->status,
            'actor_user_id' => $actor->user_id,
        ]);

        return $year;
    }

    public function startPromotionSeason(ChurchSchoolYear $year, User $actor): ChurchSchoolYear
    {
        if (! SchoolYearStatus::canStartPromotion($year->status)) {
            throw ValidationException::withMessages([
                'year' => [__('church_cycle.cannot_start_promotion', ['status' => $year->status])],
            ]);
        }

        return DB::transaction(function () use ($year, $actor) {
            $year->status = SchoolYearStatus::CLOSING;
            $year->promotion_started_at = now();
            $year->save();

            $linked = $this->linkWizardServices($year);
            $this->notifyServiceAdminsForSeason($year, $actor, $linked);

            AuditLogService::recordEvent('church.cycle.promotion_started', [
                'church_school_year_id' => $year->church_school_year_id,
                'label' => $year->label,
                'linked_service_ids' => $linked->pluck('service_id')->map(fn ($id) => (int) $id)->values()->all(),
                'actor_user_id' => $actor->user_id,
            ]);

            return $year->fresh();
        });
    }

    public function closeYear(ChurchSchoolYear $year, User $actor, bool $force = false): ChurchSchoolYear
    {
        if ($year->status === SchoolYearStatus::CLOSED) {
            throw ValidationException::withMessages([
                'year' => [__('church_cycle.already_closed')],
            ]);
        }

        if (! $force) {
            $church = Church::query()->find($year->church_id);
            $pending = collect($this->dashboard($church)['rows'])
                ->filter(fn ($row) => in_array($row['status'], [
                    CycleDashboardStatus::READY,
                    CycleDashboardStatus::BLOCKED,
                ], true))
                ->values();

            if ($pending->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'year' => [__('church_cycle.close_blocked_pending', ['count' => $pending->count()])],
                ]);
            }
        }

        $year->status = SchoolYearStatus::CLOSED;
        $year->closed_at = now();
        $year->save();

        AuditLogService::recordEvent('church.cycle.year_closed', [
            'church_school_year_id' => $year->church_school_year_id,
            'label' => $year->label,
            'force' => $force,
            'actor_user_id' => $actor->user_id,
        ]);

        return $year->fresh();
    }

    public function markServiceDone(ChurchSchoolYear $year, ChurchService $service, User $actor): ChurchSchoolYear
    {
        if (! ProgressionPolicy::usesEndOfCycleWizard(
            (string) ($this->resolver->progressionPolicy($service) ?? '')
        )) {
            throw ValidationException::withMessages([
                'service' => [__('church_cycle.cannot_mark_done_policy')],
            ]);
        }

        $year->markServiceCompleted((int) $service->service_id);

        AuditLogService::recordEvent('church.cycle.service_marked_done', [
            'church_school_year_id' => $year->church_school_year_id,
            'service_id' => $service->service_id,
            'actor_user_id' => $actor->user_id,
        ]);

        return $year->fresh();
    }

    /**
     * After a successful End-of-Cycle apply: if season is closing and no eligible remain, mark done.
     */
    public function maybeMarkServiceDoneAfterApply(ChurchService $service): void
    {
        if (! Schema::hasTable('church_school_year')) {
            return;
        }

        $year = $this->currentOpenYear();
        if (! $year || ! $year->isClosing()) {
            return;
        }

        if (! $this->eligibility->serviceSupportsWizard($service)) {
            return;
        }

        try {
            $proposal = $this->wizard->propose($service);
        } catch (\Throwable) {
            return;
        }

        // Done when nobody remains ready to promote (blocked terminals may still appear).
        if (($proposal['counts']['ready'] ?? 0) === 0) {
            $year->markServiceCompleted((int) $service->service_id);
        }
    }

    /** @return Collection<int, ChurchService> */
    private function linkWizardServices(ChurchSchoolYear $year): Collection
    {
        $services = ChurchService::query()->get()->filter(
            fn (ChurchService $s) => $this->eligibility->serviceSupportsWizard($s)
        );

        if (! Schema::hasColumn('service', 'church_school_year_id')) {
            return $services->values();
        }

        foreach ($services as $service) {
            $service->church_school_year_id = $year->church_school_year_id;
            $service->save();
        }

        return $services->values();
    }

    /** @param  Collection<int, ChurchService>  $services */
    private function notifyServiceAdminsForSeason(
        ChurchSchoolYear $year,
        User $actor,
        Collection $services
    ): void {
        $title = __('church_cycle.promotion_notification_title', ['year' => $year->label]);
        $body = __('church_cycle.promotion_notification_body', [
            'year' => $year->label,
            'count' => $services->count(),
        ]);

        foreach ($services as $service) {
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

            foreach ($recipients as $user) {
                $this->notifications->createOrUpdate(
                    $user,
                    'church_cycle_promotion_season',
                    $title,
                    $body,
                    route('admin.services.cycle.show', $service),
                    ChurchSchoolYear::class,
                    (int) $year->church_school_year_id,
                    metadata: [
                        'church_school_year_id' => $year->church_school_year_id,
                        'service_id' => $service->service_id,
                    ],
                    dedupeKey: "church_cycle_promotion:{$year->church_school_year_id}:{$service->service_id}:{$user->user_id}",
                );
            }
        }
    }
}
