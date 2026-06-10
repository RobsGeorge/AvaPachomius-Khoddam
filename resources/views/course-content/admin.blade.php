@extends('layouts.app')

@section('content')
<div class="container animate-in py-4">

    {{-- Page header --}}
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="mb-0">{{ __('pages.curriculum_manage_title') }}</h1>
            <small class="text-muted fw-semibold">{{ $course->title }} — {{ $course->year }}</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('grades.admin', $course->course_id) }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-bar-chart-line"></i> {{ __('pages.grading_weights') }}
            </a>
            <a href="{{ route('graduation.show', $course->course_id) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-mortarboard"></i> {{ __('pages.graduation_title') }}
            </a>
            <a href="{{ route('curriculum.show', $course->course_id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye"></i> {{ __('pages.student_preview') }}
            </a>
        </div>
    </div>

    {{-- Module management strip --}}
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header fw-semibold text-primary">
            <i class="bi bi-collection"></i> {{ __('pages.manage_modules') }}
        </div>
        <div class="card-body">
            <div class="row g-3">
                {{-- Link existing module --}}
                @if($availableModules->isNotEmpty())
                    <div class="col-md-5">
                        <form method="POST" action="{{ route('curriculum.attach-module', $course->course_id) }}" class="d-flex gap-2">
                            @csrf
                            <select name="module_id" class="form-select form-select-sm" required>
                                <option value="">-- {{ __('pages.link_existing_module') }} --</option>
                                @foreach($availableModules as $m)
                                    <option value="{{ $m->module_id }}">{{ $m->title }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-primary text-nowrap">
                                <i class="bi bi-link-45deg"></i> {{ __('pages.link') }}
                            </button>
                        </form>
                    </div>
                    <div class="col-auto d-flex align-items-center text-muted">{{ __('pages.or') }}</div>
                @endif
                {{-- Create new module --}}
                <div class="col">
                    <form method="POST" action="{{ route('curriculum.create-attach-module', $course->course_id) }}" class="d-flex gap-2 flex-wrap">
                        @csrf
                        <input type="text" name="title" class="form-control form-control-sm"
                               placeholder="{{ __('pages.new_module_name') }}" maxlength="30" required style="min-width:150px;">
                        <input type="text" name="description" class="form-control form-control-sm"
                               placeholder="{{ __('pages.module_desc_required') }}" maxlength="255" required style="min-width:180px;">
                        <button class="btn btn-sm btn-success text-nowrap">
                            <i class="bi bi-plus-circle"></i> {{ __('pages.create_and_link') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @forelse($course->modules as $module)
        <div class="card shadow-sm mb-5">
            {{-- Module header --}}
            <div class="card-header d-flex justify-content-between align-items-center py-3"
                 style="background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;">
                <span class="fw-bold fs-5">
                    <i class="bi bi-collection-fill me-2"></i>{{ $module->title }}
                    @if($module->description)
                        <small class="fw-normal opacity-75 ms-2">{{ $module->description }}</small>
                    @endif
                </span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-white text-dark">{{ $module->lectures->count() }} {{ __('pages.lecture') }}</span>
                    <a href="{{ route('modules.edit', $module->module_id) }}"
                       class="btn btn-sm btn-light py-0 px-2" title="{{ __('pages.edit') }} {{ __('pages.module') }}">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST"
                          action="{{ route('curriculum.detach-module', [$course->course_id, $module->module_id]) }}"
                          onsubmit="return confirm(@json(__('pages.unlink_module_confirm')))">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-light py-0 px-2" title="{{ __('pages.unlink_from_course') }}">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Module schedule & sessions --}}
            <div class="card-body border-bottom bg-light-subtle">
                <div class="fw-semibold mb-3 text-muted small">
                    <i class="bi bi-calendar-week"></i> {{ __('pages.module_schedule') }}
                </div>
                @php
                    $pivot = $module->pivot;
                    $linkedSessionIds = $module->sessions->pluck('session_id')->all();
                    $status = $pivot->status ?? 'draft';
                @endphp
                <form method="POST"
                      action="{{ route('curriculum.update-module', [$course->course_id, $module->module_id]) }}">
                    @csrf @method('PUT')
                    <div class="row g-2 mb-2">
                        <div class="col-md-2">
                            <label class="form-label small mb-0">{{ __('pages.start_date') }}</label>
                            <input type="date" name="start_date" class="form-control form-control-sm"
                                   value="{{ $pivot->start_date ? \Illuminate\Support\Carbon::parse($pivot->start_date)->format('Y-m-d') : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">{{ __('pages.end_date') }}</label>
                            <input type="date" name="end_date" class="form-control form-control-sm"
                                   value="{{ $pivot->end_date ? \Illuminate\Support\Carbon::parse($pivot->end_date)->format('Y-m-d') : '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">{{ __('pages.sort_order') }}</label>
                            <input type="number" name="order_index" class="form-control form-control-sm"
                                   min="0" value="{{ $pivot->order_index ?? 0 }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">{{ __('pages.status') }}</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="draft" @selected($status === 'draft')>{{ __('pages.module_status_draft') }}</option>
                                <option value="active" @selected($status === 'active')>{{ __('pages.module_status_active') }}</option>
                                <option value="ended" @selected($status === 'ended')>{{ __('pages.module_status_ended') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="bi bi-save"></i> {{ __('pages.save_schedule') }}
                            </button>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label small mb-0">{{ __('pages.link_weekly_sessions') }}</label>
                            <select name="session_ids[]" class="form-select form-select-sm" multiple size="4">
                                @forelse($course->sessions->sortBy('session_date') as $session)
                                    <option value="{{ $session->session_id }}"
                                        @selected(in_array($session->session_id, $linkedSessionIds))>
                                        {{ $session->session_title }}
                                        — {{ $session->session_date?->format('Y-m-d') ?? __('pages.unspecified') }}
                                    </option>
                                @empty
                                    <option disabled>{{ __('pages.no_sessions_for_course') }}</option>
                                @endforelse
                            </select>
                            <div class="form-text">{{ __('pages.sessions_multiselect_hint') }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-0">{{ __('pages.module_state') }}</label>
                            <div class="d-flex flex-column gap-2">
                                @if($pivot->feedback_open ?? false)
                                    <span class="badge bg-success">
                                        <i class="bi bi-chat-square-text"></i> {{ __('pages.feedback_open') }}
                                    </span>
                                @elseif($status === 'ended')
                                    <span class="badge bg-secondary">{{ __('pages.module_status_ended') }}</span>
                                @else
                                    <span class="badge bg-info text-dark">{{ __('pages.module_status_' . $status) }}</span>
                                @endif
                                @if(!($pivot->feedback_open ?? false))
                                    <button type="submit"
                                            formaction="{{ route('curriculum.end-module', [$course->course_id, $module->module_id]) }}"
                                            formmethod="POST"
                                            class="btn btn-sm btn-warning"
                                            onclick="return confirm(@json(__('pages.confirm_end_module')))">
                                        <i class="bi bi-megaphone"></i> {{ __('pages.end_module_open_feedback') }}
                                    </button>
                                @else
                                    <small class="text-muted">
                                        {{ __('pages.module_ended_on', ['date' => $pivot->ended_at ? \Illuminate\Support\Carbon::parse($pivot->ended_at)->format('Y-m-d H:i') : '—']) }}
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Module exams --}}
            <div class="card-body border-bottom bg-light-subtle py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div class="fw-semibold text-muted small">
                        <i class="bi bi-journal-check"></i> {{ __('pages.module_exams') }}
                    </div>
                    <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-lg"></i> {{ __('pages.manage_exams') }}
                    </a>
                </div>
                @if($module->exams->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($module->exams as $exam)
                            <span class="badge bg-primary">
                                {{ $exam->exam_name }}
                                ({{ $exam->duration_minutes }} {{ __('pages.minutes') }})
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-0">{{ __('pages.no_exams_in_module') }}</p>
                @endif
            </div>

            @php
                $linkedSessionIds = $module->courseSessions->pluck('session_id');
                $orphanLectures = $module->lectures->filter(
                    fn ($lecture) => ! $lecture->session_id || ! $linkedSessionIds->contains($lecture->session_id)
                );
            @endphp

            {{-- Sessions & lectures --}}
            @if($module->courseSessions->isEmpty())
                <div class="alert alert-warning m-3 mb-0">
                    <i class="bi bi-exclamation-triangle"></i> {{ __('pages.no_sessions_in_module') }}
                    <a href="{{ route('sessions.index') }}" class="alert-link">{{ __('pages.manage_sessions') }}</a>
                </div>
            @else
                @foreach($module->courseSessions as $session)
                    <div class="border-bottom">
                        <div class="px-3 py-2 bg-light-subtle d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="fw-semibold">
                                <span class="badge bg-secondary me-1">{{ __('pages.week') }} {{ $session->week_number ?? '?' }}</span>
                                {{ $session->session_title }}
                                <span class="text-muted small fw-normal ms-1">
                                    ({{ $session->session_date?->format('Y-m-d') ?? __('pages.unspecified') }})
                                </span>
                            </div>
                            <span class="badge bg-white text-dark border">
                                {{ $session->lectures->count() }} {{ __('pages.lecture') }}
                            </span>
                        </div>

                        @if($session->lectures->isNotEmpty())
                            @include('course-content.partials.lecture-admin-table', [
                                'lectures' => $session->lectures,
                                'course' => $course,
                            ])
                        @else
                            <p class="text-muted small px-3 py-2 mb-0">{{ __('pages.no_lectures_in_session') }}</p>
                        @endif

                        <div class="card-footer bg-light">
                            <div class="fw-semibold mb-2 text-muted small">
                                <i class="bi bi-plus-circle-fill text-success"></i> {{ __('pages.add_new_lecture') }}
                            </div>
                            @include('course-content.partials.lecture-add-form', [
                                'module' => $module,
                                'course' => $course,
                                'session' => $session,
                            ])
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- Lectures not linked to a session --}}
            @if($orphanLectures->isNotEmpty())
                <div class="border-top">
                    <div class="px-3 py-2 bg-warning-subtle">
                        <div class="fw-semibold small text-warning-emphasis">
                            <i class="bi bi-exclamation-circle"></i> {{ __('pages.unassigned_lectures') }}
                        </div>
                        <div class="small text-muted">{{ __('pages.unassigned_lectures_hint') }}</div>
                    </div>
                    @include('course-content.partials.lecture-admin-table', [
                        'lectures' => $orphanLectures,
                        'course' => $course,
                    ])
                </div>
            @endif
        </div>
    @empty
        <div class="alert alert-info">
            {{ __('pages.no_modules_for_course') }}
            {{ __('pages.add_modules_hint') }}
        </div>
    @endforelse

</div>
@endsection
