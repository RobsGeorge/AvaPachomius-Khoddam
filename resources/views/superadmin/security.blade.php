@extends('layouts.app')

@section('title', __('pages.superadmin_security_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    @include('superadmin.partials.header', ['title' => __('pages.superadmin_security_title')])

    <div class="app-card card border-danger shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 text-danger mb-2">
                <i class="bi bi-box-arrow-right"></i> {{ __('pages.force_logout_title') }}
            </h2>
            <p class="text-muted mb-3">{{ __('pages.force_logout_hint') }}</p>
            <form method="POST"
                  action="{{ route('superadmin.sessions.flush-all') }}"
                  data-confirm="{{ __('pages.force_logout_confirm') }}"
                  onsubmit="return confirm(this.dataset.confirm);">
                @csrf
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-power"></i> {{ __('pages.force_logout_button') }}
                </button>
            </form>
        </div>
    </div>

    <div class="app-card card border-warning shadow-sm">
        <div class="card-body">
            <h2 class="h5 text-warning mb-2">
                <i class="bi bi-eye-fill"></i> {{ __('pages.impersonate_title') }}
            </h2>
            <p class="text-muted mb-3">{{ __('pages.impersonate_hint') }}</p>
            <form method="POST"
                  action="{{ route('superadmin.impersonate') }}"
                  data-confirm="{{ __('pages.impersonate_confirm') }}"
                  onsubmit="return confirm(this.dataset.confirm);">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="impersonate-user-id">{{ __('pages.user') }}</label>
                    <select name="user_id" id="impersonate-user-id" class="form-select" required>
                        <option value="">{{ __('pages.select_option') }}</option>
                        @foreach($users as $user)
                            @php
                                $roleNames = $user->roles->pluck('role_name')->unique();
                                if ($user->is_superadmin) {
                                    $roleNames = $roleNames->push(__('pages.superadmin_role'));
                                }
                                $roleLabel = $roleNames->isNotEmpty()
                                    ? $roleNames->implode(', ')
                                    : __('pages.no_roles_yet');
                            @endphp
                            <option value="{{ $user->user_id }}"
                                {{ old('user_id') == $user->user_id ? 'selected' : '' }}>
                                {{ $user->first_name }} {{ $user->second_name }}
                                ({{ $user->email }}) — {{ $roleLabel }}
                            </option>
                        @endforeach
                    </select>
                    @error('user')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-box-arrow-in-right"></i> {{ __('pages.impersonate_button') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
