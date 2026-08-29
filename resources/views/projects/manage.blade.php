@extends('layouts.app')

@section('title', __('projects.manage_title'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('projects.manage_title') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">{{ __('projects.list') }}</a>
            <a href="{{ route('projects.change-requests.index') }}" class="btn btn-outline-primary">{{ __('projects.change_requests') }}</a>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 fw-bold mb-3">{{ __('projects.create') }}</h2>
            <form method="POST" action="{{ route('projects.assessments.store') }}">
                @csrf
                @if(! $course)
                    <div class="mb-3">
                        <label class="form-label" for="course_id">{{ __('pages.course') }}</label>
                        <input type="number" name="course_id" id="course_id" class="form-control" value="{{ old('course_id') }}" required>
                    </div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="module_id">{{ __('projects.module') }}</label>
                    <select name="module_id" id="module_id" class="form-select @error('module_id') is-invalid @enderror" required>
                        <option value="">—</option>
                        @foreach($modules as $module)
                            <option value="{{ $module->module_id }}" @selected(old('module_id') == $module->module_id)>{{ $module->title }}</option>
                        @endforeach
                    </select>
                    @error('module_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="title">{{ __('projects.assessment') }}</label>
                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">{{ __('projects.description') }}</label>
                    <textarea name="description" id="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="min_team_size">{{ __('projects.min_team') }}</label>
                        <input type="number" name="min_team_size" id="min_team_size" class="form-control" min="1" max="50" value="{{ old('min_team_size', 2) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="max_team_size">{{ __('projects.max_team') }}</label>
                        <input type="number" name="max_team_size" id="max_team_size" class="form-control" min="1" max="50" value="{{ old('max_team_size', 4) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="max_points">{{ __('projects.max_points') }}</label>
                        <input type="number" name="max_points" id="max_points" class="form-control" min="0.01" max="9999.99" step="0.01" value="{{ old('max_points', 100) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="passing_percent">{{ __('projects.passing_percent') }}</label>
                        <input type="number" name="passing_percent" id="passing_percent" class="form-control" min="0" max="100" value="{{ old('passing_percent', 50) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="join_closes_at">{{ __('projects.join_closes_at') }}</label>
                        <input type="datetime-local" name="join_closes_at" id="join_closes_at"
                               class="form-control @error('join_closes_at') is-invalid @enderror"
                               value="{{ old('join_closes_at', now()->addWeek()->format('Y-m-d\TH:i')) }}" required>
                        <div class="form-text">{{ __('projects.join_closes_at_help') }}</div>
                        @error('join_closes_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="seed_pool_size">{{ __('projects.seed_pool_size') }}</label>
                        <input type="number" name="seed_pool_size" id="seed_pool_size" class="form-control" min="1" max="200" value="{{ old('seed_pool_size') }}">
                        <div class="form-text">{{ __('projects.seed_pool_help') }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="form-check">
                            <input type="hidden" name="sync_to_gradebook" value="0">
                            <input class="form-check-input" type="checkbox" value="1" name="sync_to_gradebook"
                                   id="sync_to_gradebook" @checked(old('sync_to_gradebook'))>
                            <label class="form-check-label" for="sync_to_gradebook">{{ __('projects.sync_to_gradebook') }}</label>
                        </div>
                        <div class="form-text">{{ __('projects.sync_to_gradebook_help') }}</div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-2">{{ __('projects.criteria') }}</div>
                    <p class="small text-muted">{{ __('projects.criteria_hint') }}</p>
                    <div id="criteria-wrap">
                        <div class="row g-2 mb-2">
                            <div class="col-md-8"><input type="text" name="criteria[0][title]" class="form-control" placeholder="{{ __('projects.criterion_title') }}"></div>
                            <div class="col-md-4"><input type="number" name="criteria[0][max_points]" class="form-control" min="0.01" step="0.01" placeholder="{{ __('projects.criterion_max') }}"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat-criteria>{{ __('projects.add_criterion') }}</button>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-2">{{ __('projects.subprojects') }}</div>
                    <p class="small text-muted">{{ __('projects.subprojects_hint') }}</p>
                    @error('title')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    <div id="subprojects-wrap">
                        @php $oldSubs = old('subprojects', [['title' => ''], ['title' => '']]); @endphp
                        @foreach($oldSubs as $index => $row)
                            <div class="row g-2 mb-2">
                                <div class="col-md-5">
                                    <input type="text" name="subprojects[{{ $index }}][title]" class="form-control" value="{{ $row['title'] ?? '' }}" placeholder="{{ __('projects.subproject_title') }}">
                                </div>
                                <div class="col-md-7">
                                    <input type="text" name="subprojects[{{ $index }}][requirements]" class="form-control" value="{{ $row['requirements'] ?? '' }}" placeholder="{{ __('projects.subproject_requirements') }}">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat-subprojects>{{ __('projects.add_subproject') }}</button>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="requirements">{{ __('projects.shared_requirements') }}</label>
                    <textarea name="requirements" id="requirements" class="form-control" rows="3">{{ old('requirements') }}</textarea>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-2">{{ __('projects.phases') }}</div>
                    <div id="phases-wrap">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4"><input type="text" name="phases[0][title]" class="form-control" placeholder="{{ __('projects.phase') }}"></div>
                            <div class="col-md-4"><input type="datetime-local" name="phases[0][deadline]" class="form-control"></div>
                            <div class="col-md-4"><input type="text" name="phases[0][description]" class="form-control" placeholder="{{ __('projects.description') }}"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat="phases">{{ __('projects.add_phase') }}</button>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-2">{{ __('projects.deliverables') }}</div>
                    <div id="deliverables-wrap">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4"><input type="text" name="deliverables[0][title]" class="form-control" placeholder="{{ __('projects.deliverable') }}"></div>
                            <div class="col-md-4"><input type="datetime-local" name="deliverables[0][due_at]" class="form-control"></div>
                            <div class="col-md-4"><input type="text" name="deliverables[0][description]" class="form-control" placeholder="{{ __('projects.description') }}"></div>
                            <div class="col-md-4">
                                <select name="deliverables[0][submission_type]" class="form-select" aria-label="{{ __('projects.submission_type') }}">
                                    @foreach(\App\Models\ProjectDeliverable::submissionTypes() as $type)
                                        <option value="{{ $type }}">{{ __('projects.submission_type_'.$type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="deliverables[0][file_mode]" class="form-select" aria-label="{{ __('projects.file_mode') }}">
                                    <option value="single">{{ __('projects.file_mode_single') }}</option>
                                    <option value="multi">{{ __('projects.file_mode_multi', ['max' => \App\Models\ProjectDeliverable::MAX_FILES]) }}</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-center gap-3">
                                <div class="form-check mb-0">
                                    <input type="hidden" name="deliverables[0][is_required]" value="0">
                                    <input class="form-check-input" type="checkbox" value="1" name="deliverables[0][is_required]" id="deliverable-0-required" checked>
                                    <label class="form-check-label small" for="deliverable-0-required">{{ __('projects.is_required') }}</label>
                                </div>
                                <div class="form-check mb-0">
                                    <input type="hidden" name="deliverables[0][allow_late]" value="0">
                                    <input class="form-check-input" type="checkbox" value="1" name="deliverables[0][allow_late]" id="deliverable-0-late" checked>
                                    <label class="form-check-label small" for="deliverable-0-late">{{ __('projects.allow_late') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat="deliverables">{{ __('projects.add_deliverable') }}</button>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('projects.create') }}</button>
            </form>
        </div>
    </div>

    @forelse($assessments as $assessment)
        <div class="app-card card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">{{ $assessment->title }}</h2>
                        <div class="small text-muted">{{ __('projects.module') }}: {{ $assessment->module->title ?? '—' }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge {{ $assessment->is_published ? 'bg-success' : 'bg-secondary' }}">
                            {{ $assessment->is_published ? __('projects.published_badge') : __('projects.draft_badge') }}
                        </span>
                        @if($assessment->changeRequests->isNotEmpty())
                            <span class="badge bg-warning text-dark">{{ __('projects.pending_changes', ['count' => $assessment->changeRequests->count()]) }}</span>
                        @endif
                    </div>
                </div>

                <p class="small">
                    {{ __('projects.min_team') }}: {{ $assessment->min_team_size }}
                    · {{ __('projects.max_team') }}: {{ $assessment->max_team_size }}
                    · {{ __('projects.max_points') }}: {{ number_format((float) $assessment->max_points, 1) }}
                    · {{ __('projects.passing_percent') }}: {{ (int) $assessment->passing_percent }}%
                    · {{ __('projects.seed_pool_size') }}: {{ $assessment->seed_pool_size ?: __('projects.seed_pool_auto') }}
                </p>

                @include('projects.partials.assessment-overview', [
                    'assessment' => $assessment,
                    'stats' => $overview[$assessment->project_assessment_id] ?? null,
                ])

                @include('projects.partials.join-countdown', ['assessment' => $assessment])

                <form method="POST" action="{{ route('projects.assessments.update', $assessment) }}" class="row g-2 align-items-end mb-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="title" value="{{ $assessment->title }}">
                    <input type="hidden" name="min_team_size" value="{{ $assessment->min_team_size }}">
                    <input type="hidden" name="max_team_size" value="{{ $assessment->max_team_size }}">
                    <div class="col-md-5">
                        <label class="form-label small mb-1">{{ __('projects.join_closes_at') }}</label>
                        <input type="datetime-local" name="join_closes_at" class="form-control form-control-sm"
                               value="{{ optional($assessment->join_closes_at)->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">{{ __('projects.seed_pool_size') }}</label>
                        <input type="number" name="seed_pool_size" class="form-control form-control-sm" min="1" max="200"
                               value="{{ $assessment->seed_pool_size }}">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mb-2">
                            <input type="hidden" name="sync_to_gradebook" value="0">
                            <input class="form-check-input" type="checkbox" value="1" name="sync_to_gradebook"
                                   id="sync-gradebook-{{ $assessment->project_assessment_id }}"
                                   @checked($assessment->sync_to_gradebook)>
                            <label class="form-check-label small" for="sync-gradebook-{{ $assessment->project_assessment_id }}">
                                {{ __('projects.sync_to_gradebook') }}
                            </label>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary">{{ __('projects.assessment_updated_action') }}</button>
                    </div>
                </form>

                @if($assessment->sync_to_gradebook)
                    <p class="small text-muted">
                        @if($assessment->gradebook_synced_at)
                            {{ __('projects.gradebook_synced_at', ['when' => $assessment->gradebook_synced_at->format('Y-m-d H:i')]) }}
                        @else
                            {{ __('projects.gradebook_sync_pending') }}
                        @endif
                    </p>
                @endif

                @php
                    $belowMinimum = $assessment->projects->filter(fn ($p) => $p->below_minimum && ! $p->isCancelled());
                    $seatableTargets = $assessment->projects->filter(fn ($p) => ! $p->isCancelled());
                @endphp
                @if($assessment->hasJoinWindowClosed() && $belowMinimum->isNotEmpty())
                    <div class="alert alert-warning py-2">
                        <div class="fw-semibold">{{ __('projects.rescue_title') }}</div>
                        <div class="small">{{ __('projects.rescue_hint') }}</div>
                    </div>
                @endif

                @foreach($assessment->projects as $project)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div class="flex-grow-1">
                                <div class="small text-muted">{{ __('projects.subproject') }}</div>
                                <a href="{{ route('projects.show', $project) }}" class="fw-semibold">{{ $project->title }}</a>
                                <div class="small text-muted">
                                    {{ __('projects.fill') }}:
                                    {{ __('projects.seats_of', [
                                        'current' => $project->activeMemberships->count(),
                                        'max' => $assessment->max_team_size,
                                    ]) }}
                                </div>
                                <form method="POST" action="{{ route('projects.update', $project) }}" class="row g-2 align-items-end mt-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-8">
                                        <input type="text" name="title" class="form-control form-control-sm" value="{{ $project->title }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-sm btn-outline-secondary">{{ __('projects.rename_subproject') }}</button>
                                    </div>
                                </form>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1">
                                <span class="badge {{ $project->isClosed() ? 'bg-success' : 'bg-info text-dark' }}">
                                    {{ $project->isClosed() ? __('projects.team_closed') : __('projects.team_open') }}
                                </span>
                                @if($project->isLocked())
                                    <span class="badge bg-dark">{{ __('projects.locked_badge') }}</span>
                                @endif
                                @if($project->below_minimum)
                                    <span class="badge bg-warning text-dark">{{ __('projects.below_minimum_badge') }}</span>
                                @endif
                                @if($project->isCancelled())
                                    <span class="badge bg-danger">{{ __('projects.cancelled_badge') }}</span>
                                @endif
                            </div>
                        </div>

                        @if($project->below_minimum && ! $project->isCancelled())
                            <p class="small text-warning-emphasis mt-2 mb-0">{{ __('projects.below_minimum_hint') }}</p>
                        @endif

                        @php $teamProgress = $submissionProgress[$project->project_id] ?? null; @endphp
                        @if($teamProgress && $teamProgress['required'] > 0)
                            <p class="small mt-2 mb-0">
                                <span class="badge {{ $teamProgress['missing'] === 0 ? 'bg-success' : 'bg-light text-dark border' }}">
                                    {{ __('projects.deliverables_progress', [
                                        'submitted' => $teamProgress['required'] - $teamProgress['missing'],
                                        'required' => $teamProgress['required'],
                                    ]) }}
                                </span>
                                @if($teamProgress['late'] > 0)
                                    <span class="badge bg-warning text-dark">{{ __('projects.late') }}: {{ $teamProgress['late'] }}</span>
                                @endif
                            </p>
                        @endif

                        <form method="POST" action="{{ route('projects.workspace.update', $project) }}" class="row g-2 align-items-end mt-2">
                            @csrf
                            <div class="col-md-5">
                                <label class="form-label small mb-1">{{ __('projects.team_workspace_url') }}</label>
                                <input type="url" name="team_workspace_url" class="form-control form-control-sm"
                                       value="{{ $project->team_workspace_url }}" placeholder="https://">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-1">{{ __('projects.team_announcement') }}</label>
                                <input type="text" name="team_announcement" class="form-control form-control-sm"
                                       value="{{ $project->team_announcement }}">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-sm btn-outline-secondary">{{ __('projects.save_workspace') }}</button>
                            </div>
                        </form>

                        @if($project->activeMemberships->isNotEmpty())
                            <ul class="list-unstyled mt-3 mb-0">
                                @foreach($project->activeMemberships as $row)
                                    <li class="d-flex flex-wrap align-items-center gap-2 border-top py-2">
                                        <span class="fw-semibold">{{ $row->user?->displayName() }}</span>
                                        <span class="small text-muted">{{ $row->user?->mobile_number ?: __('projects.phone_missing') }}</span>
                                        <form method="POST" action="{{ route('projects.members.move', $row) }}" class="d-flex gap-1 ms-auto">
                                            @csrf
                                            <select name="to_project_id" class="form-select form-select-sm" required>
                                                <option value="">{{ __('projects.move_to_team') }}</option>
                                                @foreach($seatableTargets as $target)
                                                    @if((int) $target->project_id !== (int) $project->project_id)
                                                        <option value="{{ $target->project_id }}">{{ $target->title }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-secondary">{{ __('projects.move_member') }}</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <form method="POST" action="{{ route('projects.lock', $project) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-dark">
                                    {{ $project->isLocked() ? __('projects.unlock_team') : __('projects.lock_team') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('projects.cancel', $project) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">
                                    {{ $project->isCancelled() ? __('projects.restore_team') : __('projects.cancel_team') }}
                                </button>
                            </form>
                            @if($project->activeMemberships->isNotEmpty() && $seatableTargets->count() > 1)
                                <form method="POST" action="{{ route('projects.merge', $project) }}" class="d-flex gap-1">
                                    @csrf
                                    <select name="into_project_id" class="form-select form-select-sm" required>
                                        <option value="">{{ __('projects.merge_team') }}</option>
                                        @foreach($seatableTargets as $target)
                                            @if((int) $target->project_id !== (int) $project->project_id)
                                                <option value="{{ $target->project_id }}">{{ $target->title }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-warning">{{ __('projects.merge_submit') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="d-flex flex-wrap gap-2 border-top pt-3">
                    <a href="{{ route('projects.grades', $assessment) }}" class="btn btn-sm btn-outline-primary">{{ __('projects.grades') }}</a>
                    <a href="{{ route('projects.export', $assessment) }}" class="btn btn-sm btn-outline-secondary">{{ __('projects.export_csv') }}</a>
                    <form method="POST" action="{{ route('projects.assessments.publish', $assessment) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-success">{{ $assessment->is_published ? __('projects.unpublish') : __('projects.publish') }}</button>
                    </form>
                    @if($assessment->memberships()->doesntExist())
                        <form method="POST" action="{{ route('projects.assessments.destroy', $assessment) }}" onsubmit="return confirm(@json(__('projects.delete')));">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('projects.delete') }}</button>
                        </form>
                    @endif
                </div>

                <form method="POST" action="{{ route('projects.store', $assessment) }}" class="mt-3">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md-7">
                            <label class="form-label">{{ __('projects.add_subproject') }}</label>
                            <input type="text" name="title" class="form-control" required placeholder="{{ __('projects.subproject_title') }}">
                        </div>
                        <div class="col-md-5">
                            <button class="btn btn-outline-primary">{{ __('projects.add_subproject') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <p class="text-muted">{{ __('projects.empty_manage') }}</p>
    @endforelse
</div>

@include('projects.partials.countdown-script')

@php
    $deliverableTypeLabels = [];
    foreach (\App\Models\ProjectDeliverable::submissionTypes() as $deliverableType) {
        $deliverableTypeLabels[$deliverableType] = __('projects.submission_type_'.$deliverableType);
    }
    $deliverableFileModeLabels = [
        'single' => __('projects.file_mode_single'),
        'multi' => __('projects.file_mode_multi', ['max' => \App\Models\ProjectDeliverable::MAX_FILES]),
    ];
    $deliverableFieldLabels = [
        'is_required' => __('projects.is_required'),
        'allow_late' => __('projects.allow_late'),
        'submission_type' => __('projects.submission_type'),
        'file_mode' => __('projects.file_mode'),
    ];
@endphp
<script>
var deliverableTypeOptions = @json($deliverableTypeLabels);
var deliverableFileModes = @json($deliverableFileModeLabels);
var deliverableLabels = @json($deliverableFieldLabels);

function deliverableTypeFields(index) {
    var typeOptions = Object.keys(deliverableTypeOptions).map(function (value) {
        return '<option value="' + value + '">' + deliverableTypeOptions[value] + '</option>';
    }).join('');
    var modeOptions = Object.keys(deliverableFileModes).map(function (value) {
        return '<option value="' + value + '">' + deliverableFileModes[value] + '</option>';
    }).join('');

    return '<div class="col-md-4"><select name="deliverables[' + index + '][submission_type]" class="form-select" aria-label="' + deliverableLabels.submission_type + '">' + typeOptions + '</select></div>' +
        '<div class="col-md-4"><select name="deliverables[' + index + '][file_mode]" class="form-select" aria-label="' + deliverableLabels.file_mode + '">' + modeOptions + '</select></div>' +
        '<div class="col-md-4 d-flex align-items-center gap-3">' +
        '<div class="form-check mb-0">' +
        '<input type="hidden" name="deliverables[' + index + '][is_required]" value="0">' +
        '<input class="form-check-input" type="checkbox" value="1" name="deliverables[' + index + '][is_required]" id="deliverable-' + index + '-required" checked>' +
        '<label class="form-check-label small" for="deliverable-' + index + '-required">' + deliverableLabels.is_required + '</label>' +
        '</div>' +
        '<div class="form-check mb-0">' +
        '<input type="hidden" name="deliverables[' + index + '][allow_late]" value="0">' +
        '<input class="form-check-input" type="checkbox" value="1" name="deliverables[' + index + '][allow_late]" id="deliverable-' + index + '-late" checked>' +
        '<label class="form-check-label small" for="deliverable-' + index + '-late">' + deliverableLabels.allow_late + '</label>' +
        '</div>' +
        '</div>';
}

document.querySelectorAll('[data-repeat]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-repeat');
        var wrap = document.getElementById(key + '-wrap');
        var index = wrap.children.length;
        var second = key === 'phases' ? 'deadline' : 'due_at';
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2';
        row.innerHTML =
            '<div class="col-md-4"><input type="text" name="' + key + '[' + index + '][title]" class="form-control"></div>' +
            '<div class="col-md-4"><input type="datetime-local" name="' + key + '[' + index + '][' + second + ']" class="form-control"></div>' +
            '<div class="col-md-4"><input type="text" name="' + key + '[' + index + '][description]" class="form-control"></div>' +
            (key === 'deliverables' ? deliverableTypeFields(index) : '');
        wrap.appendChild(row);
    });
});
document.querySelectorAll('[data-repeat-subprojects]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = document.getElementById('subprojects-wrap');
        var index = wrap.children.length;
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2';
        row.innerHTML =
            '<div class="col-md-5"><input type="text" name="subprojects[' + index + '][title]" class="form-control"></div>' +
            '<div class="col-md-7"><input type="text" name="subprojects[' + index + '][requirements]" class="form-control"></div>';
        wrap.appendChild(row);
    });
});
document.querySelectorAll('[data-repeat-criteria]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = document.getElementById('criteria-wrap');
        var index = wrap.children.length;
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2';
        row.innerHTML =
            '<div class="col-md-8"><input type="text" name="criteria[' + index + '][title]" class="form-control"></div>' +
            '<div class="col-md-4"><input type="number" name="criteria[' + index + '][max_points]" class="form-control" min="0.01" step="0.01"></div>';
        wrap.appendChild(row);
    });
});
</script>
@endsection
