<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LoginTrial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'activity');

        $activityLogs = $this->activityQuery($request)
            ->with('user')
            ->orderByDesc('activity_log_id')
            ->paginate(30, ['*'], 'activity_page')
            ->withQueryString();

        $loginTrials = LoginTrial::with('user')
            ->when($request->filled('context'), fn ($q) => $q->where('context', $request->input('context')))
            ->when($request->filled('email'), fn ($q) => $q->where('email', 'like', '%'.$request->input('email').'%'))
            ->when($request->has('success') && $request->input('success') !== '', fn ($q) => $q->where('success', (bool) $request->input('success')))
            ->orderByDesc('login_trial_id')
            ->paginate(30, ['*'], 'login_page')
            ->withQueryString();

        return view('superadmin.audit.index', compact('tab', 'activityLogs', 'loginTrials'));
    }

    /** F-09 — export the (filtered) activity log as CSV for offline analysis. */
    public function exportActivity(Request $request): StreamedResponse
    {
        $filename = 'audit-activity-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $columns = ['created_at', 'user', 'http_method', 'route_name', 'url', 'ip_address', 'device_summary', 'response_status'];

        return response()->stream(function () use ($request, $columns) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Arabic device/user strings correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            $this->activityQuery($request)
                ->with('user')
                ->orderByDesc('activity_log_id')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $log) {
                        fputcsv($out, [
                            optional($log->created_at)->toDateTimeString(),
                            $log->user?->email ?? (string) $log->user_id,
                            $log->http_method,
                            $log->route_name,
                            $log->url,
                            $log->ip_address,
                            $log->device_summary,
                            $log->response_status,
                        ]);
                    }
                });

            fclose($out);
        }, 200, $headers);
    }

    /** Shared, filterable activity-log query used by both the table and the export. */
    private function activityQuery(Request $request): Builder
    {
        return ActivityLog::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->input('user_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('http_method', $request->input('method')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->input('q');
                $q->where(function ($inner) use ($term) {
                    $inner->where('url', 'like', "%{$term}%")
                        ->orWhere('route_name', 'like', "%{$term}%")
                        ->orWhere('device_summary', 'like', "%{$term}%")
                        ->orWhere('ip_address', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->when($request->input('module') === 'events', fn ($q) => $q->where('route_name', 'like', 'events.action.%'));
    }
}
