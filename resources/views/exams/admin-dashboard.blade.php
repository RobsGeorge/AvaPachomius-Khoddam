@extends('layouts.app')

@section('title', __('pages.exams_management'))

@section('content')
<div class="container py-4">
    <h1 class="page-title mb-4">{{ __('pages.exams_management') }}</h1>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="app-card card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="badge rounded-pill bg-primary p-3"><i class="bi bi-journal-text fs-4"></i></span>
                    <div>
                        <div class="text-muted-theme small">{{ __('pages.total_exams') }}</div>
                        <div class="fs-3 fw-bold">{{ $totalExams }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="badge rounded-pill bg-success p-3"><i class="bi bi-clock fs-4"></i></span>
                    <div>
                        <div class="text-muted-theme small">{{ __('pages.upcoming_exams') }}</div>
                        <div class="fs-3 fw-bold">{{ $upcomingExams }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="badge rounded-pill bg-warning text-dark p-3"><i class="bi bi-check-circle fs-4"></i></span>
                    <div>
                        <div class="text-muted-theme small">{{ __('pages.completed_exams') }}</div>
                        <div class="fs-3 fw-bold">{{ $completedExams }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('pages.quick_actions') }}</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('exams.dashboard') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> {{ __('pages.add_new_exam') }}
                </a>
                <a href="{{ route('exams.dashboard') }}" class="btn btn-outline-theme">
                    <i class="bi bi-calendar-plus"></i> {{ __('pages.schedule_exam') }}
                </a>
                <a href="{{ route('exams.dashboard') }}" class="btn btn-outline-theme">
                    <i class="bi bi-bar-chart"></i> {{ __('pages.view_results') }}
                </a>
            </div>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">{{ __('pages.upcoming_exams') }}</span>
            <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-outline-theme">{{ __('pages.view_all') }}</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.exam_name') }}</th>
                            <th>{{ __('pages.module') }}</th>
                            <th>{{ __('pages.date') }}</th>
                            <th>{{ __('pages.duration') }}</th>
                            <th>{{ __('pages.registered_count') }}</th>
                            <th>{{ __('pages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($upcomingExamSchedules as $schedule)
                            <tr>
                                <td>{{ $schedule->exam->exam_name }}</td>
                                <td>{{ $schedule->exam->module->title ?? '—' }}</td>
                                <td>{{ $schedule->scheduled_date->format('Y-m-d H:i') }}</td>
                                <td>{{ $schedule->exam->duration_minutes }} {{ __('pages.minutes') }}</td>
                                <td>{{ $schedule->results->count() }}</td>
                                <td>
                                    <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-link">{{ __('pages.edit') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted-theme py-4">—</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="app-card card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">{{ __('pages.recent_results') }}</span>
            <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-outline-theme">{{ __('pages.view_all') }}</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.student') }}</th>
                            <th>{{ __('pages.exam_name') }}</th>
                            <th>{{ __('pages.date') }}</th>
                            <th>{{ __('pages.grade') }}</th>
                            <th>{{ __('pages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentResults as $result)
                            <tr>
                                <td>{{ $result->user->name }}</td>
                                <td>
                                    {{ $result->exam->exam_name }}
                                    @if($result->exam->module)
                                        <span class="text-muted-theme small d-block">{{ $result->exam->module->title }}</span>
                                    @endif
                                </td>
                                <td>{{ $result->schedule->scheduled_date->format('Y-m-d') }}</td>
                                <td>{{ $result->score }}</td>
                                <td>
                                    <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-link">{{ __('pages.edit') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted-theme py-4">—</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
