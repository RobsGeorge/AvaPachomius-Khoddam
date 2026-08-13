@extends('layouts.app')

@section('title', __('pages.module_assessment_roster_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:1000px;">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('pages.module_assessment_roster_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ $course->title }} — {{ $module->title }}</p>
        </div>
        <a href="{{ route('curriculum.admin', $course) }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
    </div>

    <div class="alert alert-secondary small">
        <i class="bi bi-shield-lock"></i> {{ __('pages.assessment_staff_only_notice') }}
    </div>

    <div class="app-card card">
        <div class="table-responsive d-none d-lg-block">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('pages.student') }}</th>
                        <th>{{ __('pages.assessment_total') }}</th>
                        <th>{{ __('pages.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        @php $row = $existing->get($student->user_id); @endphp
                        <tr>
                            <td>{{ $student->displayName() }}</td>
                            <td>{{ $row?->total_score !== null ? $row->total_score : '—' }}</td>
                            <td>
                                @if($row)
                                    <span class="badge bg-{{ $row->status === 'final' ? 'success' : 'warning' }}">
                                        {{ __('pages.assessment_status_'.$row->status) }}
                                    </span>
                                @else
                                    <span class="text-muted">{{ __('pages.assessment_not_started') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('module-assessments.edit', [$course, $module, $student]) }}"
                                   class="btn btn-sm btn-outline-primary">{{ __('pages.assess_student') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">{{ __('pages.no_enrolled_students') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-lg-none p-3">
            @forelse($students as $student)
                @php $row = $existing->get($student->user_id); @endphp
                <article class="border-bottom py-3">
                    <div class="fw-semibold">{{ $student->displayName() }}</div>
                    <div class="small text-muted mb-2">
                        {{ __('pages.assessment_total') }}:
                        {{ $row?->total_score !== null ? $row->total_score : '—' }}
                        ·
                        @if($row)
                            {{ __('pages.assessment_status_'.$row->status) }}
                        @else
                            {{ __('pages.assessment_not_started') }}
                        @endif
                    </div>
                    <a href="{{ route('module-assessments.edit', [$course, $module, $student]) }}"
                       class="btn btn-sm btn-outline-primary w-100">{{ __('pages.assess_student') }}</a>
                </article>
            @empty
                <p class="text-muted mb-0">{{ __('pages.no_enrolled_students') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
