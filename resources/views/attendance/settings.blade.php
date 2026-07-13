@extends('layouts.app')

@section('title', __('pages.attendance_settings_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width: 720px;">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('pages.attendance_settings_title') }}</h1>
        <p class="text-muted small mb-0">{{ __('pages.attendance_settings_hint') }}</p>
    </div>

<div class="app-card card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.attendance-settings.update') }}">
                @csrf
                @method('PUT')

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_enabled" id="is_enabled" value="1"
                           {{ old('is_enabled', $policy->is_enabled) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="is_enabled">{{ __('pages.late_policy_enabled') }}</label>
                    <div class="form-text">{{ __('pages.late_policy_enabled_hint') }}</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="late_threshold_minutes">{{ __('pages.late_threshold_minutes') }}</label>
                    <div class="input-group">
                        <input type="number" name="late_threshold_minutes" id="late_threshold_minutes"
                               class="form-control @error('late_threshold_minutes') is-invalid @enderror"
                               min="0" max="240" step="1" required
                               value="{{ old('late_threshold_minutes', $policy->late_threshold_minutes) }}">
                        <span class="input-group-text">{{ __('pages.minutes') }}</span>
                    </div>
                    <div class="form-text">{{ __('pages.late_threshold_minutes_hint') }}</div>
                    @error('late_threshold_minutes')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="late_grade_percentage">{{ __('pages.late_grade_percentage') }}</label>
                    <div class="input-group">
                        <input type="number" name="late_grade_percentage" id="late_grade_percentage"
                               class="form-control @error('late_grade_percentage') is-invalid @enderror"
                               min="0" max="100" step="0.5" required
                               value="{{ old('late_grade_percentage', $policy->late_grade_percentage) }}">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">{{ __('pages.late_grade_percentage_hint') }}</div>
                    @error('late_grade_percentage')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> {{ __('pages.save') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
