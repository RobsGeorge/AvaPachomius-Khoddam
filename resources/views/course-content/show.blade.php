@extends('layouts.app')

@section('content')
<div class="container animate-in py-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h1 class="mb-0">{{ $course->title }}</h1>
            <small class="text-muted">{{ $course->description }} &mdash; {{ $course->year }}</small>
        </div>
        @if($canManageCurriculum)
            <a href="{{ route('curriculum.admin', $course->course_id) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil-square"></i> {{ __('pages.manage_curriculum') }}
            </a>
        @endif
    </div>

@forelse($course->modules as $module)
        @php
            $feedbackOpen = (bool) ($module->pivot->feedback_open ?? false);
            $surveysForModule = ($moduleSurveys->get($module->module_id) ?? collect());
            if (! $canManageFeedback) {
                $surveysForModule = $surveysForModule->reject(
                    fn ($survey) => $survey->status === \App\Models\FeedbackSurvey::STATUS_DRAFT
                )->values();
            }
        @endphp
        <div class="card shadow-sm mb-4">
            {{-- Module header --}}
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"
                 style="background: linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff;">
                <span class="fw-bold fs-5">
                    <i class="bi bi-collection-fill me-2"></i>{{ $module->title }}
                </span>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if($module->pivot->start_date || $module->pivot->end_date)
                        <span class="badge bg-white text-dark">
                            {{ $module->pivot->start_date ? \Illuminate\Support\Carbon::parse($module->pivot->start_date)->format('Y-m-d') : '…' }}
                            —
                            {{ $module->pivot->end_date ? \Illuminate\Support\Carbon::parse($module->pivot->end_date)->format('Y-m-d') : '…' }}
                        </span>
                    @endif
                    <span class="badge bg-white text-dark">
                        {{ $module->lectures->count() }} {{ __('pages.lecture') }}
                    </span>
                    @if($feedbackOpen)
                        <span class="badge bg-success">
                            <i class="bi bi-chat-square-text"></i> {{ __('pages.feedback_open') }}
                        </span>
                    @endif
                    @if($canManageCurriculum && $canManageFeedback)
                        <a href="{{ route('feedback.index') }}" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-chat-square-text"></i> {{ __('pages.manage_feedback') }}
                        </a>
                        <a href="{{ route('feedback.surveys.create', ['course_id' => $course->course_id, 'module_id' => $module->module_id]) }}"
                           class="btn btn-sm btn-light text-dark">
                            <i class="bi bi-plus-lg"></i> {{ __('pages.feedback_create_survey') }}
                        </a>
                    @endif
                </div>
            </div>

            @if($module->description)
                <div class="px-3 pt-2 text-muted small">{{ $module->description }}</div>
            @endif

            @if($feedbackOpen)
                @include('course-content.partials.module-surveys', [
                    'module' => $module,
                    'course' => $course,
                    'surveys' => $surveysForModule,
                    'submittedSurveyIds' => $submittedSurveyIds,
                    'canManageFeedback' => $canManageFeedback,
                    'variant' => 'student',
                ])
            @endif

            @if($module->exams->isNotEmpty())
                <div class="px-3 py-2 border-bottom bg-light">
                    <small class="text-muted fw-semibold d-block mb-1">{{ __('pages.module_exams') }}:</small>
                    @foreach($module->exams as $exam)
                        <span class="badge bg-primary me-1 mb-1">
                            <i class="bi bi-journal-check me-1"></i>{{ $exam->exam_name }}
                            ({{ $exam->duration_minutes }} {{ __('pages.minutes') }})
                        </span>
                    @endforeach
                </div>
            @endif

            @php
                $linkedSessionIds = $module->courseSessions->pluck('session_id');
                $orphanLectures = $module->lectures->filter(
                    fn ($lecture) => ! $lecture->session_id || ! $linkedSessionIds->contains($lecture->session_id)
                );
            @endphp

            <div class="card-body p-0">
                @if($module->courseSessions->isEmpty() && $orphanLectures->isEmpty())
                    <p class="text-center text-muted-theme py-4 mb-0">{{ __('pages.no_lectures') }}</p>
                @else
                    @foreach($module->courseSessions as $session)
                        <div class="border-bottom">
                            <div class="px-3 py-2 bg-light-subtle d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="fw-semibold">
                                    <span class="badge bg-secondary me-1">{{ __('pages.week') }} {{ $session->week_number ?? '?' }}</span>
                                    {{ $session->session_title }}
                                    <span class="text-muted small fw-normal ms-1">
                                        ({{ $session->session_date?->format('Y-m-d') ?? '—' }})
                                    </span>
                                </div>
                                <span class="badge bg-light text-dark border">
                                    {{ $session->lectures->count() }} {{ __('pages.lecture') }}
                                </span>
                            </div>

                            @if($session->lectures->isEmpty())
                                <p class="text-center text-muted-theme py-3 mb-0 small">{{ __('pages.no_lectures_in_session') }}</p>
                            @else
                                @include('course-content.partials.lecture-student-cards', ['lectures' => $session->lectures])
                            @endif
                        </div>
                    @endforeach

                    @if($orphanLectures->isNotEmpty())
                        <div class="border-top">
                            <div class="px-3 py-2 bg-light-subtle fw-semibold small text-muted">
                                {{ __('pages.unassigned_lectures') }}
                            </div>
                            @include('course-content.partials.lecture-student-cards', ['lectures' => $orphanLectures, 'showWeek' => true])
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @empty
        <div class="alert alert-info">{{ __('pages.no_modules_for_course') }}</div>
    @endforelse

</div>
@endsection
