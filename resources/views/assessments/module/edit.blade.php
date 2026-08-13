@extends('layouts.app')

@section('title', __('pages.assess_student'))

@section('content')
<div class="container py-4 animate-in" style="max-width:900px;">
    <a href="{{ route('module-assessments.index', [$course, $module]) }}" class="btn btn-outline-secondary btn-sm mb-3">{{ __('pages.back') }}</a>

    <h1 class="page-title mb-1">{{ $user->displayName() }}</h1>
    <p class="text-muted-theme mb-3">{{ $course->title }} — {{ $module->title }}</p>

    <div class="alert alert-secondary small">
        <i class="bi bi-shield-lock"></i> {{ __('pages.assessment_staff_only_notice') }}
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="app-card card mb-4">
        <div class="card-header fw-semibold">{{ __('pages.module_assessment_form_title') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('module-assessments.update', [$course, $module, $user]) }}">
                @csrf
                @method('PUT')
                @foreach($criteria as $criterion)
                    <div class="mb-3">
                        <label class="form-label" for="score_{{ $criterion->criterion_id }}">
                            {{ $criterion->label() }}
                            <span class="text-muted small">({{ __('pages.assessment_weight') }}: {{ $criterion->weight }})</span>
                        </label>
                        <input type="number" min="0" max="10" step="1"
                               class="form-control"
                               id="score_{{ $criterion->criterion_id }}"
                               name="scores[{{ $criterion->criterion_id }}]"
                               value="{{ old('scores.'.$criterion->criterion_id, $scoresByCriterion->get($criterion->criterion_id)) }}">
                    </div>
                @endforeach

                @if($assessment?->total_score !== null)
                    <p class="fw-semibold">{{ __('pages.assessment_total') }}: {{ $assessment->total_score }} / 100</p>
                @endif

                <div class="mb-3">
                    <label class="form-label">{{ __('pages.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="draft" @selected(old('status', $assessment->status ?? 'draft') === 'draft')>{{ __('pages.assessment_status_draft') }}</option>
                        <option value="final" @selected(old('status', $assessment->status ?? 'draft') === 'final')>{{ __('pages.assessment_status_final') }}</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">{{ __('pages.save_assessment') }}</button>
            </form>
        </div>
    </div>

    @if(Auth::user()->canInCourse('student_notes.view', $course))
        <div class="app-card card">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>{{ __('pages.instructor_notes_title') }}</span>
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('module-assessments.edit', [$course, $module, $user, 'notes' => 'module']) }}"
                       class="btn btn-outline-secondary @if($noteFilter === 'module') active @endif">{{ __('pages.notes_filter_module') }}</a>
                    <a href="{{ route('module-assessments.edit', [$course, $module, $user, 'notes' => 'course']) }}"
                       class="btn btn-outline-secondary @if($noteFilter === 'course') active @endif">{{ __('pages.notes_filter_course') }}</a>
                    <a href="{{ route('module-assessments.edit', [$course, $module, $user, 'notes' => 'all']) }}"
                       class="btn btn-outline-secondary @if($noteFilter === 'all') active @endif">{{ __('pages.notes_filter_all') }}</a>
                </div>
            </div>
            <div class="card-body">
                <p class="small text-muted">{{ __('pages.instructor_notes_anonymous_hint') }}</p>

                @forelse($notes as $note)
                    <div class="border-bottom py-3">
                        <div class="small text-muted mb-1">
                            {{ $note->created_at?->format('Y-m-d H:i') }}
                            @if($note->course)
                                · {{ $note->course->title }}
                            @endif
                            @if($note->module)
                                · {{ $note->module->title }}
                            @endif
                        </div>
                        <div>{{ $note->body }}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('pages.instructor_notes_empty') }}</p>
                @endforelse

                @if($canManageNotes)
                    <hr>
                    <form method="POST" action="{{ route('student-notes.store', [$course, $user]) }}">
                        @csrf
                        <input type="hidden" name="module_id" value="{{ $module->module_id }}">
                        <input type="hidden" name="redirect_module_id" value="{{ $module->module_id }}">
                        <input type="hidden" name="notes_filter" value="{{ $noteFilter }}">
                        <div class="mb-2">
                            <label class="form-label" for="note_body">{{ __('pages.instructor_note_add') }}</label>
                            <textarea name="body" id="note_body" class="form-control" rows="3" required maxlength="5000"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('pages.save_note') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
