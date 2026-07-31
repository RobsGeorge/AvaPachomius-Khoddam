<?php

namespace App\Services;

use App\Models\ChurchApplication;
use App\Models\ChurchService;
use App\Models\Course;
use App\Models\CourseApplication;
use App\Models\ServiceApplication;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserServiceRole;
use App\Services\RolePreviewService;
use App\Support\Applications\ApplicationQueueItem;
use App\Tenancy\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ApplicationsHubQuery
{
    /** Soft cap for in-memory merge before pagination (v1). */
    public const MERGE_CAP = 200;

    public const PER_PAGE = 30;

    /**
     * @return array{items: LengthAwarePaginator, counts: array<string, int>, type_counts: array<string, int>, can_see: array<string, bool>}
     */
    public function paginate(User $viewer, ?string $typeFilter, ?string $statusFilter, int $page = 1): array
    {
        $canSee = $this->visibleTypes($viewer);
        abort_unless(in_array(true, $canSee, true), 403);

        if ($typeFilter !== null && $typeFilter !== '' && ! ($canSee[$typeFilter] ?? false)) {
            abort(403);
        }

        $items = collect();

        if (($canSee[ApplicationQueueItem::TYPE_COURSE] ?? false)
            && ($typeFilter === null || $typeFilter === '' || $typeFilter === ApplicationQueueItem::TYPE_COURSE)) {
            $items = $items->merge($this->courseItems($viewer, $statusFilter));
        }

        if (($canSee[ApplicationQueueItem::TYPE_SERVICE] ?? false)
            && ($typeFilter === null || $typeFilter === '' || $typeFilter === ApplicationQueueItem::TYPE_SERVICE)) {
            $items = $items->merge($this->serviceItems($viewer, $statusFilter));
        }

        if (($canSee[ApplicationQueueItem::TYPE_CHURCH] ?? false)
            && ($typeFilter === null || $typeFilter === '' || $typeFilter === ApplicationQueueItem::TYPE_CHURCH)) {
            $items = $items->merge($this->churchItems($statusFilter));
        }

        $sorted = $items
            ->sortByDesc(fn (ApplicationQueueItem $item) => $item->submittedAt?->getTimestamp() ?? 0)
            ->values()
            ->take(self::MERGE_CAP);

        $typeCounts = [
            ApplicationQueueItem::TYPE_COURSE => 0,
            ApplicationQueueItem::TYPE_SERVICE => 0,
            ApplicationQueueItem::TYPE_CHURCH => 0,
        ];
        $statusCounts = [];
        foreach ($sorted as $item) {
            $typeCounts[$item->type] = ($typeCounts[$item->type] ?? 0) + 1;
            $statusCounts[$item->status] = ($statusCounts[$item->status] ?? 0) + 1;
        }

        $page = max(1, $page);
        $slice = $sorted->forPage($page, self::PER_PAGE)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $sorted->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => array_filter([
                    'type' => $typeFilter ?: null,
                    'filter' => $statusFilter ?: null,
                ]),
            ]
        );

        return [
            'items' => $paginator,
            'counts' => $statusCounts,
            'type_counts' => $typeCounts,
            'can_see' => $canSee,
        ];
    }

    public function canAccessHub(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return in_array(true, $this->visibleTypes($user), true);
    }

    /** @return array<string, bool> */
    public function visibleTypes(User $viewer): array
    {
        return [
            ApplicationQueueItem::TYPE_COURSE => $viewer->canAccessAdminCourseApplications(),
            ApplicationQueueItem::TYPE_SERVICE => $viewer->canAccessAdminServiceApplications(),
            ApplicationQueueItem::TYPE_CHURCH => $viewer->canAccessAdminChurchApplications(),
        ];
    }

    /** @return Collection<int, ApplicationQueueItem> */
    private function courseItems(User $viewer, ?string $statusFilter): Collection
    {
        $query = CourseApplication::query()
            ->with(['user', 'course'])
            ->latest('submitted_at')
            ->limit(self::MERGE_CAP);

        $allowedCourseIds = $this->reviewableCourseIds($viewer);
        if ($allowedCourseIds !== null) {
            $query->whereIn('course_id', $allowedCourseIds->isEmpty() ? [-1] : $allowedCourseIds);
        }

        if ($statusFilter && in_array($statusFilter, CourseApplication::statuses(), true)) {
            $query->where('status', $statusFilter);
        }

        return $query->get()->map(function (CourseApplication $app) {
            $user = $app->user;

            return new ApplicationQueueItem(
                type: ApplicationQueueItem::TYPE_COURSE,
                id: (int) $app->getKey(),
                subjectLabel: $app->course?->localizedTitle() ?? ('#'.$app->course_id),
                applicantLabel: $user?->displayName() ?? '—',
                applicantSecondary: $user?->email,
                status: (string) $app->status,
                submittedAt: $app->submitted_at,
                showUrl: route('admin.course-applications.show', $app),
            );
        });
    }

    /** @return Collection<int, ApplicationQueueItem> */
    private function serviceItems(User $viewer, ?string $statusFilter): Collection
    {
        $query = ServiceApplication::query()
            ->with(['user', 'service'])
            ->latest('submitted_at')
            ->limit(self::MERGE_CAP);

        $allowedServiceIds = $this->reviewableServiceIds($viewer);
        if ($allowedServiceIds !== null) {
            $query->whereIn('service_id', $allowedServiceIds->isEmpty() ? [-1] : $allowedServiceIds);
        }

        $allowed = [
            ServiceApplication::STATUS_PENDING,
            ServiceApplication::STATUS_APPROVED,
            ServiceApplication::STATUS_REJECTED,
        ];
        if ($statusFilter && in_array($statusFilter, $allowed, true)) {
            $query->where('status', $statusFilter);
        }

        return $query->get()->map(function (ServiceApplication $app) {
            $user = $app->user;

            return new ApplicationQueueItem(
                type: ApplicationQueueItem::TYPE_SERVICE,
                id: (int) $app->service_application_id,
                subjectLabel: $app->service?->localizedTitle() ?? ('#'.$app->service_id),
                applicantLabel: $user?->displayName() ?? '—',
                applicantSecondary: $user?->email,
                status: (string) $app->status,
                submittedAt: $app->submitted_at,
                showUrl: route('admin.service-applications.show', $app),
            );
        });
    }

    /** @return Collection<int, ApplicationQueueItem> */
    private function churchItems(?string $statusFilter): Collection
    {
        $allowed = ChurchApplication::reviewableStatuses();

        $query = ChurchApplication::query()
            // Never surface unverified leads — email confirm happens before the review queue.
            ->whereIn('status', $allowed)
            ->latest('submitted_at')
            ->limit(self::MERGE_CAP);

        if ($statusFilter && in_array($statusFilter, $allowed, true)) {
            $query->where('status', $statusFilter);
        }

        return $query->get()->map(function (ChurchApplication $app) {
            return new ApplicationQueueItem(
                type: ApplicationQueueItem::TYPE_CHURCH,
                id: (int) $app->church_application_id,
                subjectLabel: (string) $app->requested_name,
                applicantLabel: (string) $app->contact_name,
                applicantSecondary: $app->contact_email,
                status: (string) $app->status,
                submittedAt: $app->submitted_at,
                showUrl: route('superadmin.church-applications.show', $app),
            );
        });
    }

    /**
     * null = unrestricted (system / superadmin); otherwise course IDs the reviewer may see.
     *
     * @return Collection<int, int>|null
     */
    private function reviewableCourseIds(User $admin): ?Collection
    {
        if (($admin->is_superadmin ?? false) || $admin->canInSystem('course_application.review')) {
            return null;
        }

        $courseIds = UserCourseRole::query()
            ->where('user_id', $admin->user_id)
            ->pluck('course_id')
            ->unique()
            ->filter()
            ->values();

        if ($courseIds->isEmpty()) {
            return collect();
        }

        return Course::query()
            ->withoutGlobalScope('church')
            ->whereIn('course_id', $courseIds)
            ->get()
            ->filter(fn (Course $course) => $admin->canAccessAdminCourseApplications($course))
            ->pluck('course_id')
            ->values();
    }

    /**
     * null = unrestricted within current tenant (system / superadmin when tenancy dormant);
     * otherwise explicit service IDs the reviewer may see.
     *
     * @return Collection<int, int>|null
     */
    private function reviewableServiceIds(User $admin): ?Collection
    {
        $systemWide = RolePreviewService::superadminBypassesPermissions($admin)
            || $admin->canInSystem('service_application.review');

        if ($systemWide) {
            if (TenantContext::enforced() && Schema::hasColumn('service', 'church_id')) {
                $churchId = TenantContext::id();

                return ChurchService::query()
                    ->withoutGlobalScope('church')
                    ->where('church_id', $churchId)
                    ->pluck('service_id')
                    ->values();
            }

            return null;
        }

        if (! Schema::hasTable('user_service_role')) {
            return collect();
        }

        $serviceIds = UserServiceRole::query()
            ->where('user_id', $admin->user_id)
            ->pluck('service_id')
            ->unique()
            ->filter()
            ->values();

        if ($serviceIds->isEmpty()) {
            return collect();
        }

        return ChurchService::query()
            ->withoutGlobalScope('church')
            ->whereIn('service_id', $serviceIds)
            ->get()
            ->filter(fn (ChurchService $service) => $admin->canAccessAdminServiceApplications($service))
            ->pluck('service_id')
            ->values();
    }
}
