@extends('layouts.app')

@section('title', __('service.applications_admin_title'))

@section('content')
<div class="container py-4 animate-in">
    <h1 class="page-title h4 mb-3">{{ __('service.applications_admin_title') }}</h1>
    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('pages.user') }}</th>
                        <th>{{ __('service.label') }}</th>
                        <th>{{ __('pages.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $application)
                        <tr>
                            <td>{{ $application->user?->first_name }} {{ $application->user?->second_name }}</td>
                            <td>{{ $application->service?->localizedTitle() }}</td>
                            <td>{{ $application->status }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.service-applications.show', $application) }}" class="btn btn-sm btn-outline-primary">
                                    {{ __('pages.view') ?? 'View' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small">{{ __('pages.no_records') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
