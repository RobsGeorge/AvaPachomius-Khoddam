<?php

namespace App\Observability;

use App\Models\ObservabilityEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ObservabilityReportService
{
    public function eventsQuery(Request $request, ObservabilityScope $scope, ?int $churchId = null): Builder
    {
        $query = $scope === ObservabilityScope::Platform
            // Cross-tenant platform console: intentional withoutTenancy for master view.
            ? ObservabilityEvent::withoutTenancy()
            : ObservabilityEvent::query();

        if ($scope === ObservabilityScope::Church && $churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return $query
            ->when($request->filled('church_id') && $scope === ObservabilityScope::Platform,
                fn (Builder $q) => $q->where('church_id', (int) $request->input('church_id')))
            ->when($request->filled('category'), fn (Builder $q) => $q->where('category', $request->input('category')))
            ->when($request->filled('severity'), fn (Builder $q) => $q->where('severity', $request->input('severity')))
            ->when($request->filled('fingerprint'), fn (Builder $q) => $q->where('fingerprint', $request->input('fingerprint')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = $request->input('q');
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('message', 'like', "%{$term}%")
                        ->orWhere('exception_class', 'like', "%{$term}%")
                        ->orWhere('url', 'like', "%{$term}%")
                        ->orWhere('route_name', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('from'), fn (Builder $q) => $q->where('occurred_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('occurred_at', '<=', $request->input('to').' 23:59:59'));
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function groupedIncidents(Request $request, ObservabilityScope $scope, ?int $churchId = null): LengthAwarePaginator
    {
        $base = $this->eventsQuery($request, $scope, $churchId);

        return $base
            ->select([
                'fingerprint',
                DB::raw('MAX(message) as sample_message'),
                DB::raw('MAX(exception_class) as exception_class'),
                DB::raw('MAX(severity) as severity'),
                DB::raw('MAX(category) as category'),
                DB::raw('COUNT(*) as event_count'),
                DB::raw('COUNT(DISTINCT user_id) as affected_users'),
                DB::raw('COUNT(DISTINCT church_id) as affected_churches'),
                DB::raw('MAX(occurred_at) as last_seen'),
                DB::raw('MIN(occurred_at) as first_seen'),
            ])
            ->groupBy('fingerprint')
            ->orderByDesc('last_seen')
            ->paginate(30)
            ->withQueryString();
    }

    public function authFailures(Request $request, ObservabilityScope $scope, ?int $churchId = null): LengthAwarePaginator
    {
        $request->merge(['category' => 'auth']);

        return $this->eventsQuery($request, $scope, $churchId)
            ->with('user')
            ->orderByDesc('occurred_at')
            ->paginate(40)
            ->withQueryString();
    }

    /**
     * @return Collection<int, object>
     */
    public function usageSeries(Request $request, ObservabilityScope $scope, ?int $churchId = null): Collection
    {
        $query = $scope === ObservabilityScope::Platform
            // Cross-tenant platform console: intentional withoutTenancy for master view.
            ? \App\Models\UsageRollup::withoutTenancy()
            : \App\Models\UsageRollup::query();

        if ($scope === ObservabilityScope::Church && $churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return $query
            ->when($request->filled('church_id') && $scope === ObservabilityScope::Platform,
                fn ($q) => $q->where('church_id', (int) $request->input('church_id')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', (int) $request->input('service_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('bucket_start', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('bucket_start', '<=', $request->input('to').' 23:59:59'))
            ->orderBy('bucket_start')
            ->limit(500)
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function usageByChurch(Request $request, ObservabilityScope $scope, ?int $churchId = null): Collection
    {
        $query = $scope === ObservabilityScope::Platform
            // Cross-tenant platform console: intentional withoutTenancy for master view.
            ? \App\Models\UsageRollup::withoutTenancy()
            : \App\Models\UsageRollup::query();

        if ($scope === ObservabilityScope::Church && $churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return $query
            ->when($request->filled('from'), fn ($q) => $q->where('bucket_start', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('bucket_start', '<=', $request->input('to').' 23:59:59'))
            ->selectRaw('church_id, SUM(active_users) as active_users, SUM(request_count) as request_count, SUM(unique_sessions) as unique_sessions')
            ->groupBy('church_id')
            ->orderByDesc('request_count')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, \App\Models\InfraSample>
     */
    public function infraSeries(Request $request): Collection
    {
        return \App\Models\InfraSample::query()
            ->when($request->filled('host'), fn ($q) => $q->where('host', $request->input('host')))
            ->when($request->filled('from'), fn ($q) => $q->where('sampled_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('sampled_at', '<=', $request->input('to').' 23:59:59'))
            ->orderByDesc('sampled_at')
            ->limit(300)
            ->get();
    }

    /**
     * @return Collection<int, ObservabilityEvent>
     */
    public function affectedUsersForFingerprint(string $fingerprint, ObservabilityScope $scope, ?int $churchId = null, int $limit = 50): Collection
    {
        $query = $scope === ObservabilityScope::Platform
            // Cross-tenant platform console: intentional withoutTenancy for master view.
            ? ObservabilityEvent::withoutTenancy()
            : ObservabilityEvent::query();

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return $query
            ->with('user')
            ->where('fingerprint', $fingerprint)
            ->whereNotNull('user_id')
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->unique('user_id')
            ->values();
    }
}
