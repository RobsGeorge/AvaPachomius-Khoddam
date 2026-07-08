@extends('layouts.app')

@section('title', __('pages.exams_management'))

@section('content')
<div class="container py-4 exams-hub">
    <h1 class="page-title mb-4">{{ __('pages.exams_management') }}</h1>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
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
        <div class="col-12 col-md-4">
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
        <div class="col-12 col-md-4">
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

    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 fw-semibold">{{ __('pages.upcoming_exams') }}</h5>
            <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-outline-theme">{{ __('pages.view_all') }}</a>
        </div>
        <div class="row g-3">
            @forelse($upcomingExamSchedules as $schedule)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="app-card card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">{{ $schedule->exam->exam_name }}</h6>
                            <dl class="exam-meta-list mb-3">
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.module') }}</dt>
                                    <dd>{{ $schedule->exam->module->title ?? '—' }}</dd>
                                </div>
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.date') }}</dt>
                                    <dd>{{ $schedule->scheduled_date->format('Y-m-d H:i') }}</dd>
                                </div>
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.duration') }}</dt>
                                    <dd>{{ $schedule->exam->duration_minutes }} {{ __('pages.minutes') }}</dd>
                                </div>
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.registered_count') }}</dt>
                                    <dd>{{ $schedule->results->count() }}</dd>
                                </div>
                            </dl>
                            <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-outline-theme w-100">{{ __('pages.edit') }}</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="app-tile text-center text-muted-theme py-4">—</div>
                </div>
            @endforelse
        </div>
    </div>

    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 fw-semibold">{{ __('pages.recent_results') }}</h5>
            <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-outline-theme">{{ __('pages.view_all') }}</a>
        </div>
        <div class="row g-3">
            @forelse($recentResults as $result)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="app-card card shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="fw-bold mb-1">{{ $result->user->name }}</h6>
                            <p class="text-muted small mb-3">{{ $result->exam->exam_name }}</p>
                            <dl class="exam-meta-list mb-3">
                                @if($result->exam->module)
                                    <div class="exam-meta-row">
                                        <dt>{{ __('pages.module') }}</dt>
                                        <dd>{{ $result->exam->module->title }}</dd>
                                    </div>
                                @endif
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.date') }}</dt>
                                    <dd>{{ $result->schedule->scheduled_date->format('Y-m-d') }}</dd>
                                </div>
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.grade') }}</dt>
                                    <dd class="fw-semibold">{{ $result->score }}</dd>
                                </div>
                            </dl>
                            <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-outline-theme w-100">{{ __('pages.edit') }}</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="app-tile text-center text-muted-theme py-4">—</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.exams-hub .exam-meta-list { display: flex; flex-direction: column; gap: 0.65rem; }
.exams-hub .exam-meta-row {
    display: grid;
    grid-template-columns: minmax(0, 38%) minmax(0, 1fr);
    gap: 0.5rem 0.75rem;
    align-items: start;
}
.exams-hub .exam-meta-row dt {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
    word-break: break-word;
}
.exams-hub .exam-meta-row dd {
    margin: 0;
    font-size: 0.95rem;
    word-break: break-word;
}
</style>
@endpush
