@extends('layouts.app')
@section('title', __('church_mgmt.home_visits_title'))
@section('content')
<div class="container py-4" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('church_mgmt.home_visits_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('church_mgmt.home_visits_intro') }}</p>
        </div>
        <a href="{{ route('church.home-visits.create') }}" class="btn btn-primary">{{ __('church_mgmt.add_visit') }}</a>
    </div>
    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('church_mgmt.scheduled_at') }}</th>
                        <th>{{ __('church_mgmt.subject_name') }}</th>
                        <th>{{ __('church_mgmt.assignee') }}</th>
                        <th>{{ __('church_mgmt.priest_status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visits as $visit)
                        <tr>
                            <td>{{ $visit->scheduled_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td>{{ $visit->subject_name }}</td>
                            <td>{{ $visit->assignee?->email }}</td>
                            <td>{{ __('church_mgmt.status_'.$visit->status) }}</td>
                            <td class="text-end">
                                <a href="{{ route('church.home-visits.edit', $visit) }}" class="btn btn-sm btn-outline-primary">{{ __('church_mgmt.edit_visit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted-theme py-4">{{ __('church_mgmt.no_visits') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $visits->links() }}</div>
</div>
@endsection
