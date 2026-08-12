@extends('layouts.app')

@section('title', __('church_applications.admin_title'))

@section('content')
@php
    $statusBadgeClass = [
        \App\Models\ChurchApplication::STATUS_PENDING => 'bg-warning text-dark',
        \App\Models\ChurchApplication::STATUS_APPROVED => 'bg-success',
        \App\Models\ChurchApplication::STATUS_REJECTED => 'bg-danger',
        \App\Models\ChurchApplication::STATUS_UNVERIFIED => 'bg-secondary',
    ];
@endphp
<div class="container py-4 animate-in">
    <h1 class="page-title h4 mb-1">{{ __('church_applications.admin_title') }}</h1>
    <p class="text-muted-theme mb-3">{{ __('church_applications.admin_intro') }}</p>

    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('church_applications.requested_name') }}</th>
                        <th>{{ __('church_applications.contact_name') }}</th>
                        <th>{{ __('church_applications.place_governorate') }}</th>
                        <th>{{ __('church_applications.status') }}</th>
                        <th>{{ __('church_applications.submitted_at') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $application)
                        @php
                            $countryCode = $application->place_country_code;
                            $countryLabel = filled($countryCode)
                                ? (__('countries.'.$countryCode) !== 'countries.'.$countryCode ? __('countries.'.$countryCode) : $countryCode)
                                : null;
                            $badge = $statusBadgeClass[$application->status] ?? 'bg-secondary';
                        @endphp
                        <tr>
                            <td>{{ $application->requested_name }}</td>
                            <td>
                                <div>{{ $application->contact_name }}</div>
                                <div class="small text-muted-theme">{{ $application->contact_email }}</div>
                            </td>
                            <td class="small">
                                {{ collect([
                                    $application->place_district,
                                    $application->place_governorate,
                                    $countryLabel,
                                ])->filter()->implode(' · ') ?: '—' }}
                            </td>
                            <td>
                                <span class="badge {{ $badge }}">
                                    {{ __('church_applications.status_'.$application->status) }}
                                </span>
                            </td>
                            <td class="small">{{ $application->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('superadmin.church-applications.show', $application) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    {{ __('church_applications.view') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted-theme py-4">{{ __('church_applications.no_applications') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
