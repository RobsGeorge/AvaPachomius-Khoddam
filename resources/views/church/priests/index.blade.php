@extends('layouts.app')
@section('title', __('church_mgmt.priests_title'))
@section('content')
<div class="container py-4" style="max-width:900px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('church_mgmt.priests_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('church_mgmt.priests_intro') }}</p>
        </div>
        @php
            $church = \App\Tenancy\TenantContext::current() ?? \App\Models\Church::main();
            $canManagePriests = auth()->user()?->is_superadmin
                || auth()->user()?->canInChurch('priest.manage', $church);
        @endphp
        @if($canManagePriests)
            <a href="{{ route('church.priests.create') }}" class="btn btn-primary">{{ __('church_mgmt.add_priest') }}</a>
        @endif
    </div>
    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('church_mgmt.priest_email') }}</th>
                        <th>{{ __('church_mgmt.priest_title') }}</th>
                        <th>{{ __('church_mgmt.priest_status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($priests as $priest)
                        <tr>
                            <td>{{ $priest->user?->email }}</td>
                            <td>{{ $priest->title ?: '—' }}</td>
                            <td>{{ __('church_mgmt.status_'.$priest->status) }}</td>
                            <td class="text-end">
                                @if($canManagePriests)
                                    <a href="{{ route('church.priests.edit', $priest) }}" class="btn btn-sm btn-outline-primary">{{ __('church_mgmt.edit_priest') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted-theme py-4">{{ __('church_mgmt.no_priests') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
