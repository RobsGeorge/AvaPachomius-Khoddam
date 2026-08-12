<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Observability\ObservabilityReportService;
use App\Observability\ObservabilityScope;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ObservabilityController extends Controller
{
    public function __construct(
        private readonly ObservabilityReportService $reports,
    ) {}

    public function index(Request $request)
    {
        $churchId = TenantContext::id();
        abort_unless($churchId !== null || ! config('tenancy.enabled'), 404);

        $tab = $request->query('tab', 'incidents');
        $scope = ObservabilityScope::Church;
        // When tenancy is dormant, still scope to Tenant Zero if available.
        $effectiveChurchId = $churchId ?? 1;

        $incidents = $tab === 'incidents'
            ? $this->reports->groupedIncidents($request, $scope, $effectiveChurchId)
            : null;

        $authFailures = $tab === 'auth'
            ? $this->reports->authFailures($request, $scope, $effectiveChurchId)
            : null;

        $usageSeries = $tab === 'usage'
            ? $this->reports->usageSeries($request, $scope, $effectiveChurchId)
            : null;

        $usageByChurch = $tab === 'usage'
            ? $this->reports->usageByChurch($request, $scope, $effectiveChurchId)
            : null;

        $affectedUsers = null;
        if ($tab === 'incidents' && $request->filled('fingerprint')) {
            $affectedUsers = $this->reports->affectedUsersForFingerprint(
                (string) $request->input('fingerprint'),
                $scope,
                $effectiveChurchId
            );
        }

        return view('observability.index', [
            'scope' => $scope,
            'tab' => $tab,
            'incidents' => $incidents,
            'authFailures' => $authFailures,
            'usageSeries' => $usageSeries,
            'usageByChurch' => $usageByChurch,
            'infraSeries' => null,
            'churches' => collect(),
            'affectedUsers' => $affectedUsers,
            'showLoad' => false,
            'showUsage' => true,
            'indexRoute' => 'admin.observability.index',
            'exportRoute' => 'admin.observability.export',
            'backRoute' => 'dashboard',
            'showChurchFilter' => false,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $churchId = TenantContext::id() ?? 1;
        $filename = 'church-observability-'.now()->format('Ymd-His').'.csv';
        $columns = [
            'occurred_at', 'severity', 'category', 'fingerprint', 'message',
            'exception_class', 'user_id', 'url', 'route_name', 'request_id',
        ];

        return response()->stream(function () use ($request, $columns, $churchId) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            $this->reports->eventsQuery($request, ObservabilityScope::Church, $churchId)
                ->orderByDesc('occurred_at')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $event) {
                        fputcsv($out, [
                            optional($event->occurred_at)->toDateTimeString(),
                            $event->severity,
                            $event->category,
                            $event->fingerprint,
                            $event->message,
                            $event->exception_class,
                            $event->user_id,
                            $event->url,
                            $event->route_name,
                            $event->request_id,
                        ]);
                    }
                });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
