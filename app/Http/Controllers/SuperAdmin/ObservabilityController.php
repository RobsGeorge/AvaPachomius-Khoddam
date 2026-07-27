<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Observability\ObservabilityReportService;
use App\Observability\ObservabilityScope;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ObservabilityController extends Controller
{
    public function __construct(
        private readonly ObservabilityReportService $reports,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'incidents');
        $scope = ObservabilityScope::Platform;

        $incidents = $tab === 'incidents'
            ? $this->reports->groupedIncidents($request, $scope)
            : null;

        $authFailures = $tab === 'auth'
            ? $this->reports->authFailures($request, $scope)
            : null;

        $churches = Church::query()->orderBy('name')->get(['church_id', 'name', 'slug']);

        $affectedUsers = null;
        if ($tab === 'incidents' && $request->filled('fingerprint')) {
            $affectedUsers = $this->reports->affectedUsersForFingerprint(
                (string) $request->input('fingerprint'),
                $scope
            );
        }

        return view('superadmin.observability.index', [
            'scope' => $scope,
            'tab' => $tab,
            'incidents' => $incidents,
            'authFailures' => $authFailures,
            'churches' => $churches,
            'affectedUsers' => $affectedUsers,
            'showLoad' => false,
            'showUsage' => false,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'observability-events-'.now()->format('Ymd-His').'.csv';
        $columns = [
            'occurred_at', 'severity', 'category', 'fingerprint', 'message',
            'exception_class', 'user_id', 'church_id', 'url', 'route_name', 'request_id',
        ];

        return response()->stream(function () use ($request, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            $this->reports->eventsQuery($request, ObservabilityScope::Platform)
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
                            $event->church_id,
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
