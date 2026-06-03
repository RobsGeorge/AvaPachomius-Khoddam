@extends('layouts.app')

@section('content')
<div class="container animate-in py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">{{ $course->title }}</h1>
            <small class="text-muted">{{ $course->description }} &mdash; {{ $course->year }}</small>
        </div>
        @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
            <a href="{{ route('course-content.admin', $course->course_id) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil-square"></i> {{ __('pages.manage_content') }}
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @forelse($course->modules as $module)
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
                    @if($module->pivot->feedback_open ?? false)
                        @if(isset($userFeedbackIds[$module->module_id]))
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle"></i> {{ __('pages.feedback_submitted') }}
                            </span>
                        @else
                            <a href="{{ route('module-feedback.show', [$course->course_id, $module->module_id]) }}"
                               class="btn btn-sm btn-warning">
                                <i class="bi bi-chat-square-text"></i> {{ __('pages.give_feedback') }}
                            </a>
                        @endif
                    @endif
                </div>
            </div>

            @if($module->description)
                <div class="px-3 pt-2 text-muted small">{{ $module->description }}</div>
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

            @if($module->sessions->isNotEmpty())
                <div class="px-3 py-2 border-bottom">
                    <small class="text-muted fw-semibold">{{ __('pages.linked_sessions') }}:</small>
                    @foreach($module->sessions as $session)
                        <span class="badge bg-light text-dark border me-1">
                            {{ __('pages.week') }} {{ $session->pivot->week_number ?? '?' }} —
                            {{ $session->session_title }}
                            ({{ $session->session_date?->format('Y-m-d') ?? '—' }})
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="card-body p-0">
                @if($module->lectures->isEmpty())
                    <p class="text-center text-muted-theme py-4 mb-0">{{ __('pages.no_lectures') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:50px;">#</th>
                                    <th class="text-center" style="width:80px;">{{ __('pages.week_col') }}</th>
                                    <th style="width:110px;">{{ __('pages.date') }}</th>
                                    <th>{{ __('pages.lecture') }}</th>
                                    <th class="text-center" style="width:80px;">{{ __('pages.video_col') }}</th>
                                    <th class="text-center" style="width:80px;">{{ __('pages.slides') }}</th>
                                    <th>{{ __('pages.additional_materials') }}</th>
                                    <th>{{ __('pages.notes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($module->lectures as $i => $lecture)
                                    <tr>
                                        <td class="text-center text-muted small">{{ $i + 1 }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary rounded-pill">
                                                {{ __('pages.week') }} {{ $lecture->week_number }}
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            {{ $lecture->lecture_date
                                                ? $lecture->lecture_date->format('Y-m-d')
                                                : '—' }}
                                        </td>
                                        <td class="fw-semibold">{{ $lecture->title }}</td>
                                        <td class="text-center">
                                            @if($lecture->video_link)
                                                <a href="{{ $lecture->video_link }}" target="_blank"
                                                   class="btn btn-sm btn-danger rounded-circle p-1 lh-1"
                                                   title="{{ __('pages.watch_video') }}">
                                                    <i class="bi bi-play-fill"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($lecture->slides_link)
                                                <a href="{{ $lecture->slides_link }}" target="_blank"
                                                   class="btn btn-sm btn-primary rounded-circle p-1 lh-1"
                                                   title="{{ __('pages.download_slides') }}">
                                                    <i class="bi bi-file-earmark-slides-fill"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @forelse($lecture->materials as $mat)
                                                <a href="{{ $mat->link }}" target="_blank"
                                                   class="badge bg-light text-primary border text-decoration-none me-1 mb-1 d-inline-block"
                                                   title="{{ $mat->link }}">
                                                    <i class="bi bi-link-45deg"></i> {{ $mat->title }}
                                                </a>
                                            @empty
                                                <span class="text-muted small">—</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            @if($lecture->notes)
                                                <span class="text-muted small" style="white-space:pre-line;">
                                                    {{ Str::limit($lecture->notes, 80) }}
                                                </span>
                                                @if(strlen($lecture->notes) > 80)
                                                    <a href="#" data-bs-toggle="modal"
                                                       data-bs-target="#notes-{{ $lecture->lecture_id }}"
                                                       class="small">{{ __('pages.more') }}</a>
                                                    <div class="modal fade" id="notes-{{ $lecture->lecture_id }}" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">{{ $lecture->title }}</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body" style="white-space:pre-line;">{{ $lecture->notes }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="alert alert-info">{{ __('pages.no_modules_for_course') }}</div>
    @endforelse

</div>
@endsection
