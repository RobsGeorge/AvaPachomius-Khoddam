@extends('layouts.app')
@section('title', __('church_mgmt.manage_secretaries'))
@section('content')
<div class="container py-4" style="max-width:640px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('church_mgmt.manage_secretaries') }}</h1>
            <p class="text-muted-theme mb-0">{{ $priest->displayName() }}</p>
        </div>
        <a href="{{ route('church.confession.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.back') }}</a>
    </div>

    <form method="POST" action="{{ route('church.priests.secretaries.store', $priest) }}" class="app-card card shadow-sm mb-3">
        @csrf
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="user_id">{{ __('church_mgmt.add_secretary') }}</label>
                <select name="user_id" id="user_id" class="form-select" required>
                    <option value="">{{ __('church_mgmt.select_member') }}</option>
                    @foreach($members as $member)
                        <option value="{{ $member->user_id }}">
                            {{ trim(($member->first_name ?? '').' '.($member->second_name ?? '')) ?: $member->email }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.save') }}</button>
        </div>
    </form>

    <div class="app-card card shadow-sm">
        <ul class="list-group list-group-flush">
            @forelse($priest->secretaries->where('status', 'active') as $row)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ trim(($row->user->first_name ?? '').' '.($row->user->second_name ?? '')) ?: $row->user->email }}</span>
                    <form method="POST" action="{{ route('church.priests.secretaries.remove', [$priest, $row]) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('church_mgmt.remove_secretary') }}</button>
                    </form>
                </li>
            @empty
                <li class="list-group-item text-muted-theme">{{ __('church_mgmt.no_secretaries') }}</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
