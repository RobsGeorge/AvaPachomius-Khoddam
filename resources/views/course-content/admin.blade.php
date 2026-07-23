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
            <a href="{{ route('admin.courses.application-form.edit', $course->course_id) }}" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-ui-checks"></i> {{ __('course_applications.manage_form') }}
            </a>
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

    {{-- Course details --}}
    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-journal-bookmark"></i> {{ __('pages.course_details') }}
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('courses.update-details', $course->course_id) }}" class="row g-3 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">{{ __('pages.course_title') }}</label>
                    <input type="text" name="title" class="form-control form-control-sm"
                           value="{{ old('title', $course->title) }}" maxlength="30" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">{{ __('pages.description') }}</label>
                    <input type="text" name="description" class="form-control form-control-sm"
                           value="{{ old('description', $course->description) }}" maxlength="255" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-semibold">{{ __('pages.year') }}</label>
                    <input type="number" name="year" class="form-control form-control-sm"
                           value="{{ old('year', $course->year) }}" min="2000" max="2100" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">{{ __('pages.default_session_start_time') }}</label>
                    <input type="time" name="default_session_start_time" class="form-control form-control-sm"
                           value="{{ old('default_session_start_time', $course->formattedDefaultSessionStartTime()) }}" required>
                    <div class="form-text">{{ __('pages.course_default_session_start_time_hint') }}</div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-save"></i> {{ __('pages.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Localized names and course branding --}}
    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-palette"></i> {{ __('course_context.branding_title') }}
        </div>
        <div class="card-body">
            <p class="text-muted-theme small">{{ __('course_context.branding_hint') }}</p>
            <form method="POST" action="{{ route('courses.update-details', $course->course_id) }}" class="row g-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="title" value="{{ old('title', $course->title) }}">
                <input type="hidden" name="description" value="{{ old('description', $course->description) }}">
                <input type="hidden" name="year" value="{{ old('year', $course->year) }}">
                <input type="hidden" name="default_session_start_time" value="{{ old('default_session_start_time', $course->formattedDefaultSessionStartTime()) }}">

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">{{ __('course_context.title_ar') }}</label>
                    <input type="text" name="title_ar" class="form-control form-control-sm"
                           value="{{ old('title_ar', $course->title_ar) }}" maxlength="120">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">{{ __('course_context.title_en') }}</label>
                    <input type="text" name="title_en" class="form-control form-control-sm"
                           value="{{ old('title_en', $course->title_en) }}" maxlength="120">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">{{ __('course_context.description_ar') }}</label>
                    <textarea name="description_ar" class="form-control form-control-sm" rows="2">{{ old('description_ar', $course->description_ar) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">{{ __('course_context.description_en') }}</label>
                    <textarea name="description_en" class="form-control form-control-sm" rows="2">{{ old('description_en', $course->description_en) }}</textarea>
                </div>
                @php $branding = $course->brandingColors(); @endphp
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">{{ __('course_context.theme_primary') }}</label>
                    <input type="color" name="branding_primary" class="form-control form-control-color w-100"
                           value="{{ old('branding_primary', $branding['primary'] ?? '#7c3aed') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">{{ __('course_context.theme_accent') }}</label>
                    <input type="color" name="branding_accent" class="form-control form-control-color w-100"
                           value="{{ old('branding_accent', $branding['accent'] ?? '#d4af37') }}">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="p-3 rounded border w-100" style="background: var(--color-surface); border-color: var(--color-border) !important;">
                        <div class="small text-muted-theme mb-2">{{ __('course_context.current_course') }}</div>
                        <div class="fw-semibold" style="color: var(--color-primary);">{{ $course->localizedTitle() }}</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="inherit_theme" value="1" class="form-check-input" id="inherit_theme"
                               @checked(old('inherit_theme', empty($course->branding_theme)))>
                        <label class="form-check-label" for="inherit_theme">{{ __('course_context.inherit_theme') }}</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-save"></i> {{ __('pages.save') }}
                    </button>
                </div>
            </form>
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
                          data-confirm="{{ __('pages.unlink_module_confirm') }}">
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
                    </div>
                </form>
                <div class="row g-2 mt-2">
                    <div class="col-md-8 d-none d-md-block" aria-hidden="true"></div>
                    <div class="col-md-4">
                        <label class="form-label small mb-0">{{ __('pages.module_state') }}</label>
                        <div class="d-flex flex-column gap-2">
                            @if($pivot->feedback_open ?? false)
                                <span class="badge bg-success">
                                    <i class="bi bi-chat-square-text"></i> {{ __('pages.feedback_open') }}
                                </span>
                                <small class="text-muted">
                                    {{ __('pages.module_ended_on', ['date' => $pivot->ended_at ? \Illuminate\Support\Carbon::parse($pivot->ended_at)->format('Y-m-d H:i') : '—']) }}
                                </small>
                            @elseif($status === 'ended')
                                <span class="badge bg-secondary">{{ __('pages.module_status_ended') }}</span>
                            @else
                                <span class="badge bg-info text-dark">{{ __('pages.module_status_' . $status) }}</span>
                            @endif

                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('feedback.surveys.create', ['course_id' => $course->course_id, 'module_id' => $module->module_id]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-lg"></i> {{ __('pages.feedback_create_survey') }}
                                </a>
                                <a href="{{ route('feedback.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-chat-square-text"></i> {{ __('pages.manage_feedback') }}
                                </a>
                            </div>

                            @if(!($pivot->feedback_open ?? false))
                                {{-- Own POST form: must not sit inside the PUT schedule form (_method spoof would 405). --}}
                                <form method="POST"
                                      action="{{ route('curriculum.end-module', [$course->course_id, $module->module_id]) }}"
                                      data-confirm="{{ __('pages.confirm_end_module') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <i class="bi bi-megaphone"></i> {{ __('pages.end_module_open_feedback') }}
                                    </button>
                                </form>
                            @endif

                            @include('course-content.partials.module-surveys', [
                                'module' => $module,
                                'course' => $course,
                                'surveys' => $moduleSurveys->get($module->module_id) ?? collect(),
                                'canManageFeedback' => true,
                                'variant' => 'admin',
                            ])
                        </div>
                    </div>
                </div>
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
