<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LoginTrial;
use Illuminate\Http\Request;

class SuperAdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'activity');

        $activityLogs = ActivityLog::with('user')
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
            ->when($request->input('module') === 'events', fn ($q) => $q->where('route_name', 'like', 'events.action.%'))
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
}
