@extends('layouts.app')

@section('title', __('pages.graduation_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title mb-0">{{ __('pages.graduation_title') }}</h1>
            <p class="text-muted small mb-0">{{ __('pages.graduation_index_hint') }}</p>
        </div>
        @if(auth()->user()->is_superadmin || auth()->user()->roles->contains('role_name', 'admin'))
            <a href="{{ route('admin.graduation-settings.index') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-sliders"></i> {{ __('pages.graduation_configure_criteria') }}
            </a>
        @endif
    </div>

    @if($unconfiguredCount > 0)
        <div class="alert alert-warning d-flex gap-3 align-items-start mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
            <div>
                <strong>{{ __('pages.graduation_criteria_not_set') }}</strong>
                <p class="mb-2 mt-1">{{ __('pages.graduation_criteria_not_set_body', ['count' => $unconfiguredCount]) }}</p>
                @if(auth()->user()->is_superadmin || auth()->user()->roles->contains('role_name', 'admin'))
                    <a href="{{ route('admin.graduation-settings.index') }}" class="btn btn-sm btn-warning">
                        {{ __('pages.graduation_configure_criteria') }}
                    </a>
                @endif
            </div>
        </div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive d-none d-lg-block admin-table-desktop">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.course') }}</th>
                            <th>{{ __('pages.year') }}</th>
                            <th>{{ __('pages.students') }}</th>
                            <th>{{ __('pages.graduation_eligible_count') }}</th>
                            <th>{{ __('pages.passing_percentage') }}</th>
                            <th>{{ __('pages.min_attendance_percentage') }}</th>
                            <th>{{ __('pages.status') }}</th>
                            <th>{{ __('pages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summaries as $row)
                            <tr class="{{ ! $row['configured'] ? 'table-warning' : '' }}">
                                <td class="fw-semibold">{{ $row['course']->title }}</td>
                                <td>{{ $row['course']->year }}</td>
                                <td>{{ $row['students'] }}</td>
                                <td>
                                    @if($row['configured'])
                                        <span class="badge bg-success">{{ $row['eligible'] }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row['course']->passing_percentage !== null)
                                        {{ number_format($row['course']->passing_percentage, 1) }}%
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row['course']->min_attendance_percentage !== null)
                                        {{ number_format($row['course']->min_attendance_percentage, 1) }}%
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $lifecycle = match($row['course']->status ?? 'active') {
                                            'grading_locked' => __('course_graduation.status_grading_locked'),
                                            'announced' => __('course_graduation.status_announced'),
                                            'closed' => __('course_graduation.status_closed'),
                                            'archived' => __('course_graduation.status_archived'),
                                            default => __('course_graduation.status_active'),
                                        };
                                    @endphp
                                    <span class="badge bg-secondary mb-1">{{ $lifecycle }}</span><br>
                                    @if($row['configured'])
                                        <span class="badge bg-success">{{ __('pages.graduation_criteria_configured') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('graduation.show', $row['course']->course_id) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-mortarboard"></i> {{ __('pages.view_graduation') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted-theme py-4">{{ __('pages.no_courses_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-lg-none admin-data-cards student-data-hub p-3">
                @forelse($summaries as $row)
                    <article class="data-card {{ ! $row['configured'] ? 'border-warning' : '' }}">
                        <div class="data-card-title">{{ $row['course']->title }}</div>
                        <dl class="data-meta-list mb-3">
                            <div class="data-meta-row">
                                <dt>{{ __('pages.year') }}</dt>
                                <dd>{{ $row['course']->year }}</dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.students') }}</dt>
                                <dd>{{ $row['students'] }}</dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.graduation_eligible_count') }}</dt>
                                <dd>
                                    @if($row['configured'])
                                        <span class="badge bg-success">{{ $row['eligible'] }}</span>
                                    @else — @endif
                                </dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.passing_percentage') }}</dt>
                                <dd>
                                    @if($row['course']->passing_percentage !== null)
                                        {{ number_format($row['course']->passing_percentage, 1) }}%
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.min_attendance_percentage') }}</dt>
                                <dd>
                                    @if($row['course']->min_attendance_percentage !== null)
                                        {{ number_format($row['course']->min_attendance_percentage, 1) }}%
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.status') }}</dt>
                                <dd>
                                    @if($row['configured'])
                                        <span class="badge bg-success">{{ __('pages.graduation_criteria_configured') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        <div class="data-card-actions">
                            <a href="{{ route('graduation.show', $row['course']->course_id) }}"
                               class="btn btn-sm btn-outline-primary w-100">
                                <i class="bi bi-mortarboard"></i> {{ __('pages.view_graduation') }}
                            </a>
                        </div>
                    </article>
                @empty
                    <p class="text-center text-muted-theme py-4 mb-0">{{ __('pages.no_courses_yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
