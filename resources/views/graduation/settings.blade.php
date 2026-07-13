@extends('layouts.app')

@section('title', __('pages.graduation_settings_admin_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <a href="{{ route('graduation.index') }}" class="text-muted small">{{ __('pages.graduation_title') }}</a>
            <h1 class="page-title mb-1">{{ __('pages.graduation_settings_admin_title') }}</h1>
            <p class="text-muted small mb-0">{{ __('pages.graduation_settings_admin_hint') }}</p>
        </div>
    </div>

<div class="app-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive d-none d-lg-block admin-table-desktop">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.course') }}</th>
                            <th>{{ __('pages.year') }}</th>
                            <th style="min-width:140px;">{{ __('pages.passing_percentage') }}</th>
                            <th style="min-width:140px;">{{ __('pages.min_attendance_percentage') }}</th>
                            <th>{{ __('pages.status') }}</th>
                            <th>{{ __('pages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courses as $course)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $course->title }}</div>
                                    <div class="text-muted-theme small text-truncate" style="max-width:220px;">{{ $course->description }}</div>
                                </td>
                                <td>{{ $course->year }}</td>
                                <td colspan="3">
                                    <form method="POST" action="{{ route('admin.graduation-settings.update', $course->course_id) }}"
                                          class="row g-2 align-items-center">
                                        @csrf @method('PUT')
                                        <div class="col-md-3">
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="passing_percentage" class="form-control"
                                                       min="0" max="100" step="0.5" required
                                                       placeholder="60"
                                                       value="{{ old('passing_percentage', $course->passing_percentage) }}">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="min_attendance_percentage" class="form-control"
                                                       min="0" max="100" step="0.5" required
                                                       placeholder="75"
                                                       value="{{ old('min_attendance_percentage', $course->min_attendance_percentage) }}">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            @if($course->hasGraduationCriteria())
                                                <span class="badge bg-success">{{ __('pages.graduation_criteria_configured') }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                                            @endif
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="bi bi-save"></i> {{ __('pages.save') }}
                                            </button>
                                            <a href="{{ route('graduation.show', $course->course_id) }}"
                                               class="btn btn-sm btn-outline-theme">
                                                {{ __('pages.view_graduation') }}
                                            </a>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted-theme py-4">{{ __('pages.no_courses_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-lg-none admin-data-cards student-data-hub p-3">
                @forelse($courses as $course)
                    <article class="data-card">
                        <div class="data-card-title">{{ $course->title }}</div>
                        <p class="small text-muted-theme mb-2">{{ $course->year }} — {{ Str::limit($course->description, 120) }}</p>
                        <form method="POST" action="{{ route('admin.graduation-settings.update', $course->course_id) }}" class="d-flex flex-column gap-2">
                            @csrf @method('PUT')
                            <div>
                                <label class="form-label small mb-1">{{ __('pages.passing_percentage') }}</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="passing_percentage" class="form-control"
                                           min="0" max="100" step="0.5" required
                                           value="{{ old('passing_percentage', $course->passing_percentage) }}">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label small mb-1">{{ __('pages.min_attendance_percentage') }}</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="min_attendance_percentage" class="form-control"
                                           min="0" max="100" step="0.5" required
                                           value="{{ old('min_attendance_percentage', $course->min_attendance_percentage) }}">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div>
                                @if($course->hasGraduationCriteria())
                                    <span class="badge bg-success">{{ __('pages.graduation_criteria_configured') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                                @endif
                            </div>
                            <div class="data-card-actions d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-save"></i> {{ __('pages.save') }}
                                </button>
                                <a href="{{ route('graduation.show', $course->course_id) }}"
                                   class="btn btn-sm btn-outline-theme">
                                    {{ __('pages.view_graduation') }}
                                </a>
                            </div>
                        </form>
                    </article>
                @empty
                    <p class="text-center text-muted-theme py-4 mb-0">{{ __('pages.no_courses_yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
