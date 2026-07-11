@extends('layouts.app')

@section('title', __('course_applications.queue_title'))

@section('content')
@php
    use App\Models\CourseApplication;
@endphp
<div class="container-fluid py-4 animate-in student-data-hub">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('course_applications.queue_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('course_applications.queue_intro') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" class="row g-2 mb-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">{{ __('course_applications.filter_course') }}</label>
            <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('course_applications.filter_all') }}</option>
                @foreach($courses as $course)
                    <option value="{{ $course->course_id }}" @selected($courseFilter == $course->course_id)>
                        {{ $course->title }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="d-flex flex-wrap gap-2 mb-3 admin-filter-bar">
        <a href="{{ route('admin.course-applications.index', request()->only('course_id')) }}"
           class="btn btn-sm {{ $filter ? 'btn-outline-secondary' : 'btn-primary' }}">
            {{ __('course_applications.filter_all') }} ({{ array_sum($counts) }})
        </a>
        @foreach(CourseApplication::statuses() as $statusKey)
            <a href="{{ route('admin.course-applications.index', array_filter(['filter' => $statusKey, 'course_id' => $courseFilter])) }}"
               class="btn btn-sm {{ $filter === $statusKey ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('course_applications.status_'.$statusKey) }} ({{ $counts[$statusKey] ?? 0 }})
            </a>
        @endforeach
    </div>

    <div class="table-responsive app-card card shadow-sm">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('course_applications.applicant') }}</th>
                    <th>{{ __('course_applications.course') }}</th>
                    <th>{{ __('course_applications.submitted_at') }}</th>
                    <th>{{ __('course_applications.version') }}</th>
                    <th>{{ __('registration_review.waiting_status_label') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($applications as $application)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $application->user?->displayName() }}</div>
                            <div class="small text-muted-theme">{{ $application->user?->email }}</div>
                        </td>
                        <td>{{ $application->course?->title }}</td>
                        <td>{{ $application->submitted_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $application->version }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ __('course_applications.status_'.$application->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.course-applications.show', $application) }}" class="btn btn-sm btn-primary">
                                {{ __('course_applications.review') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted-theme py-4">{{ __('course_applications.no_applications') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
