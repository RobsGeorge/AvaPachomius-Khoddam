@extends('layouts.app')
@section('title', __('church_mgmt.appointment_types'))
@section('content')
<div class="container py-4" style="max-width:800px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="page-title mb-0">{{ __('church_mgmt.appointment_types') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('church.appointments.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.back') }}</a>
            <a href="{{ route('church.appointments.types.create') }}" class="btn btn-primary">{{ __('church_mgmt.add_appointment_type') }}</a>
        </div>
    </div>
    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('church_mgmt.name_ar') }}</th>
                        <th>{{ __('church_mgmt.name_en') }}</th>
                        <th>{{ __('church_mgmt.capacity') }}</th>
                        <th>{{ __('church_mgmt.duration_minutes') }}</th>
                        <th>{{ __('church_mgmt.priest_status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($types as $type)
                        <tr>
                            <td>{{ $type->name_ar }}</td>
                            <td>{{ $type->name_en ?: '—' }}</td>
                            <td>{{ $type->default_capacity }}</td>
                            <td>{{ $type->default_duration_minutes }}</td>
                            <td>{{ __('church_mgmt.status_'.$type->status) }}</td>
                            <td class="text-end">
                                <a href="{{ route('church.appointments.types.edit', $type) }}" class="btn btn-sm btn-outline-primary">{{ __('church_mgmt.edit_slot') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted-theme py-4">{{ __('church_mgmt.no_appointment_types') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
