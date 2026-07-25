@extends('layouts.app')

@section('title', __('events.event_admins_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    @include('superadmin.partials.header', ['title' => __('events.event_admins_title')])

    <div class="app-card card shadow-sm">
        <div class="card-header fw-semibold">
            <i class="bi bi-calendar-event"></i> {{ __('events.event_admins_title') }}
        </div>
        <div class="card-body p-0">
            <p class="small text-muted-theme px-3 pt-3 mb-2">{{ __('events.event_admins_hint') }}</p>
            <div class="table-responsive table-responsive-compact">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>{{ __('pages.user') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($eventAdmins as $ea)
                            <tr>
                                <td>
                                    {{ $ea->user->first_name ?? '—' }} {{ $ea->user->second_name ?? '' }}
                                    <div class="text-muted small">{{ $ea->user->email ?? '—' }}</div>
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('superadmin.event-admins.destroy', $ea->user_id) }}"
                                          data-confirm="{{ __('pages.confirm_delete') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-outline-danger py-0 px-1">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted-theme py-2">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <form method="POST" action="{{ route('superadmin.event-admins.store') }}" class="d-flex gap-2">
                @csrf
                <select name="user_id" class="form-select form-select-sm" required>
                    <option value="">{{ __('pages.select_option') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->user_id }}">{{ $u->first_name }} {{ $u->second_name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-outline-theme text-nowrap">
                    <i class="bi bi-plus"></i>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
