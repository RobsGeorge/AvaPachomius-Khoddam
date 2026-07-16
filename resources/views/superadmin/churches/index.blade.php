@extends('layouts.app')

@section('title', __('tenancy.churches_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('tenancy.churches_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('tenancy.churches_intro') }}</p>
        </div>
        <a href="{{ route('superadmin.churches.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> {{ __('tenancy.create_church') }}
        </a>
    </div>

    @unless($tenancyEnabled)
        <div class="alert alert-warning">{{ __('tenancy.multi_tenant_off_hint') }}</div>
    @endunless

    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('tenancy.col_name') }}</th>
                        <th>{{ __('tenancy.col_slug') }}</th>
                        <th>{{ __('tenancy.col_host') }}</th>
                        <th>{{ __('tenancy.col_status') }}</th>
                        <th>{{ __('tenancy.col_members') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($churches as $church)
                        <tr>
                            <td class="fw-semibold">{{ $church->name }}</td>
                            <td><code>{{ $church->slug }}</code></td>
                            <td class="small">{{ \App\Support\ChurchHost::hostFor($church) }}</td>
                            <td>
                                <span class="badge {{ $church->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ __('tenancy.status_'.$church->status) }}
                                </span>
                            </td>
                            <td>{{ $church->members_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('superadmin.churches.show', $church) }}" class="btn btn-sm btn-outline-primary">
                                    {{ __('tenancy.manage') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted-theme text-center py-4">{{ __('tenancy.no_churches') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
