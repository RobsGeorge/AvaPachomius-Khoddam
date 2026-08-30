@extends('layouts.app')

@section('title', __('projects.grades_title'))

@section('content')
@php
    $criteria = $assessment->criteria;
    $hasCriteria = $criteria->isNotEmpty();
@endphp
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <a href="{{ route('projects.manage') }}" class="small">&larr; {{ __('projects.manage_title') }}</a>
            <h1 class="page-title mb-1">{{ __('projects.grades_title') }}: {{ $assessment->title }}</h1>
            <p class="text-muted small mb-0">
                {{ __('projects.module') }}: {{ $assessment->module->title ?? '—' }}
                · {{ __('projects.max_points') }}: {{ number_format((float) $maxPoints, 1) }}
                · {{ __('projects.passing_percent') }}: {{ (int) $assessment->passing_percent }}%
            </p>
            @if($assessment->areResultsAnnounced())
                <p class="small text-success mb-0 mt-1">
                    {{ __('projects.results_announced_at', ['when' => $assessment->results_announced_at->format('Y-m-d H:i')]) }}
                </p>
            @else
                <p class="small text-muted mb-0 mt-1">{{ __('projects.results_not_announced_yet') }}</p>
            @endif
        </div>
        <div>
            @unless($assessment->areResultsAnnounced())
                <form method="POST" action="{{ route('projects.grades.announce', $assessment) }}"
                      onsubmit="return confirm(@json(__('projects.confirm_announce_results')))">
                    @csrf
                    <button type="submit" class="btn btn-success">{{ __('projects.announce_results') }}</button>
                </form>
            @endunless
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 fw-bold mb-3">{{ __('projects.scale') }}</h2>
            <form method="POST" action="{{ route('projects.grades.scale', $assessment) }}" class="row g-2 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-md-3">
                    <label class="form-label" for="max_points">{{ __('projects.max_points') }}</label>
                    <input type="number" name="max_points" id="max_points" class="form-control"
                           min="0.01" max="9999.99" step="0.01"
                           value="{{ number_format((float) $assessment->max_points, 2, '.', '') }}"
                           @disabled($hasCriteria && ! $assessment->usesDeliverableGrading())>
                    @if($hasCriteria && ! $assessment->usesDeliverableGrading())
                        <div class="form-text">{{ __('projects.max_from_criteria') }}</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="passing_percent">{{ __('projects.passing_percent') }}</label>
                    <input type="number" name="passing_percent" id="passing_percent" class="form-control"
                           min="0" max="100" value="{{ (int) $assessment->passing_percent }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="grading_mode">{{ __('projects.grading_mode') }}</label>
                    <select name="grading_mode" id="grading_mode" class="form-select">
                        <option value="rubric" @selected(! $assessment->usesDeliverableGrading())>{{ __('projects.grading_mode_rubric') }}</option>
                        <option value="deliverables" @selected($assessment->usesDeliverableGrading())>{{ __('projects.grading_mode_deliverables') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-primary">{{ __('projects.save_scale') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 fw-bold mb-2">{{ __('projects.criteria') }}</h2>
            <p class="small text-muted">{{ __('projects.criteria_hint') }}</p>
            <form method="POST" action="{{ route('projects.grades.criteria', $assessment) }}">
                @csrf
                @method('PUT')
                <div id="grade-criteria-wrap">
                    @forelse($criteria as $index => $criterion)
                        <div class="row g-2 mb-2">
                            <input type="hidden" name="criteria[{{ $index }}][project_grade_criterion_id]" value="{{ $criterion->project_grade_criterion_id }}">
                            <div class="col-md-8">
                                <input type="text" name="criteria[{{ $index }}][title]" class="form-control" value="{{ $criterion->title }}" required>
                            </div>
                            <div class="col-md-4">
                                <input type="number" name="criteria[{{ $index }}][max_points]" class="form-control" min="0.01" step="0.01" value="{{ number_format((float) $criterion->max_points, 2, '.', '') }}" required>
                            </div>
                        </div>
                    @empty
                        <div class="row g-2 mb-2">
                            <div class="col-md-8">
                                <input type="text" name="criteria[0][title]" class="form-control" placeholder="{{ __('projects.criterion_title') }}">
                            </div>
                            <div class="col-md-4">
                                <input type="number" name="criteria[0][max_points]" class="form-control" min="0.01" step="0.01" placeholder="{{ __('projects.criterion_max') }}">
                            </div>
                        </div>
                    @endforelse
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-repeat-grade-criteria>{{ __('projects.add_criterion') }}</button>
                <div>
                    <button class="btn btn-primary">{{ __('projects.save_criteria') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 fw-bold mb-2">{{ __('projects.peer_eval_settings') }}</h2>
            <p class="small text-muted">{{ __('projects.peer_eval_settings_hint') }}</p>
            <form method="POST" action="{{ route('projects.peer-eval.update', $assessment) }}" class="row g-2 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input type="hidden" name="peer_eval_enabled" value="0">
                        <input class="form-check-input" type="checkbox" value="1" name="peer_eval_enabled" id="peer_eval_enabled"
                               @checked(old('peer_eval_enabled', $assessment->peer_eval_enabled))>
                        <label class="form-check-label" for="peer_eval_enabled">{{ __('projects.peer_eval_enabled') }}</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="peer_eval_opens_at">{{ __('projects.peer_eval_opens_at') }}</label>
                    <input type="datetime-local" name="peer_eval_opens_at" id="peer_eval_opens_at" class="form-control"
                           value="{{ old('peer_eval_opens_at', optional($assessment->peer_eval_opens_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="peer_eval_closes_at">{{ __('projects.peer_eval_closes_at') }}</label>
                    <input type="datetime-local" name="peer_eval_closes_at" id="peer_eval_closes_at" class="form-control"
                           value="{{ old('peer_eval_closes_at', optional($assessment->peer_eval_closes_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="peer_eval_scale_max">{{ __('projects.peer_eval_scale_max') }}</label>
                    <input type="number" name="peer_eval_scale_max" id="peer_eval_scale_max" class="form-control" min="1" max="10"
                           value="{{ old('peer_eval_scale_max', $assessment->peer_eval_scale_max ?: 5) }}">
                </div>
                <div class="col-12">
                    <label class="form-label" for="peer_eval_prompt">{{ __('projects.peer_eval_prompt') }}</label>
                    <textarea name="peer_eval_prompt" id="peer_eval_prompt" class="form-control" rows="2">{{ old('peer_eval_prompt', $assessment->peer_eval_prompt) }}</textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-outline-primary">{{ __('projects.peer_eval_settings_save') }}</button>
                </div>
            </form>
        </div>
    </div>

    @foreach($assessment->projects as $project)
        @php
            $teamGrade = $project->teamGrade;
            $rubric = $teamRubrics[$project->project_id] ?? ['criteria' => [], 'custom' => false];
            $effectiveCriteria = $rubric['criteria'];
        @endphp
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-0">
                            {{ $project->title }}
                            @if($rubric['custom'])
                                <span class="badge bg-info text-dark">{{ __('projects.team_rubric_custom_badge') }}</span>
                            @endif
                        </h2>
                        <div class="small text-muted">
                            {{ __('projects.seats_of', [
                                'current' => $project->activeMemberships->count(),
                                'max' => $assessment->max_team_size,
                            ]) }}
                        </div>
                    </div>
                    @if($teamGrade)
                        <span class="badge bg-secondary">
                            {{ number_format((float) $teamGrade->points, 1) }}/{{ number_format((float) $maxPoints, 1) }}
                            · {{ number_format((float) $teamGrade->percent, 1) }}%
                        </span>
                    @endif
                </div>

                <form method="POST" action="{{ route('projects.grades.team', $project) }}" class="mb-4">
                    @csrf
                    @if($assessment->usesDeliverableGrading())
                        @foreach(($deliverableBreakdowns[$project->project_id] ?? []) as $row)
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-md-6">
                                    {{ $row['title'] }} ({{ number_format($row['max_points'], 1) }})
                                </div>
                                <div class="col-md-6">
                                    <input type="number"
                                           name="scores[{{ $row['project_deliverable_id'] }}]"
                                           class="form-control"
                                           min="0"
                                           max="{{ number_format($row['max_points'], 2, '.', '') }}"
                                           step="0.01"
                                           value="{{ $row['points'] !== null ? number_format($row['points'], 2, '.', '') : '' }}"
                                           required>
                                </div>
                            </div>
                        @endforeach
                    @elseif($effectiveCriteria !== [])
                        @foreach($effectiveCriteria as $criterion)
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-md-6">
                                    {{ $criterion['title'] }} ({{ number_format($criterion['max_points'], 1) }})
                                    @if($criterion['kind'] === 'team')
                                        <span class="badge bg-light text-dark border">{{ __('projects.team_rubric_custom_badge') }}</span>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <input type="number"
                                           name="scores[{{ $criterion['key'] }}]"
                                           class="form-control"
                                           min="0"
                                           max="{{ number_format($criterion['max_points'], 2, '.', '') }}"
                                           step="0.01"
                                           value="{{ $criterion['points'] !== null ? number_format($criterion['points'], 2, '.', '') : '' }}"
                                           required>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="mb-2">
                            <label class="form-label">{{ __('projects.team_points') }}</label>
                            <input type="number" name="points" class="form-control" min="0" max="{{ number_format((float) $maxPoints, 2, '.', '') }}" step="0.01"
                                   value="{{ $teamGrade ? number_format((float) $teamGrade->points, 2, '.', '') : '' }}" required>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">{{ __('projects.grade_notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2">{{ $teamGrade?->notes }}</textarea>
                    </div>
                    <button class="btn btn-outline-primary">{{ __('projects.save_team_grade') }}</button>
                </form>

                @if($hasCriteria && ! $assessment->usesDeliverableGrading())
                    @include('projects.partials.team-rubric-form', [
                        'assessment' => $assessment,
                        'project' => $project,
                        'criteria' => $criteria,
                        'effectiveCriteria' => $effectiveCriteria,
                        'maxPoints' => $maxPoints,
                        'isCustom' => $rubric['custom'],
                    ])
                @endif

                <h3 class="h6 fw-bold">{{ __('projects.student_overrides') }}</h3>
                <p class="small text-muted">{{ __('projects.student_overrides_hint') }}</p>
                <ul class="list-unstyled mb-0">
                    @forelse($project->activeMemberships as $membership)
                        @php
                            $memberGrade = $memberGrades->get($membership->user_id);
                        @endphp
                        <li class="border rounded p-2 mb-2">
                            <div class="fw-semibold">{{ $membership->user?->displayName() ?? ('#'.$membership->user_id) }}</div>
                            <div class="small text-muted mb-2">
                                @if($memberGrade)
                                    {{ number_format((float) $memberGrade->points, 1) }}
                                    ({{ number_format((float) $memberGrade->percent, 1) }}%)
                                    · {{ $memberGrade->isOverride() ? __('projects.source_override') : __('projects.source_team') }}
                                @else
                                    {{ __('projects.grade_not_entered') }}
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('projects.grades.student', [$assessment, $membership->user]) }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="number" name="points" class="form-control form-control-sm" style="width: 7rem;" min="0" max="{{ number_format((float) $maxPoints, 2, '.', '') }}" step="0.01" required
                                           value="{{ $memberGrade ? number_format((float) $memberGrade->points, 2, '.', '') : '' }}">
                                    <button class="btn btn-sm btn-outline-secondary">{{ __('projects.save_student_grade') }}</button>
                                </form>
                                @if($memberGrade?->isOverride())
                                    <form method="POST" action="{{ route('projects.grades.student.clear', [$assessment, $membership->user]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">{{ __('projects.clear_override') }}</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('projects.no_members_yet') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endforeach
</div>

<script>
document.querySelectorAll('[data-repeat-grade-criteria]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = document.getElementById('grade-criteria-wrap');
        var index = wrap.children.length;
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2';
        row.innerHTML =
            '<div class="col-md-8"><input type="text" name="criteria[' + index + '][title]" class="form-control"></div>' +
            '<div class="col-md-4"><input type="number" name="criteria[' + index + '][max_points]" class="form-control" min="0.01" step="0.01"></div>';
        wrap.appendChild(row);
    });
});
document.querySelectorAll('[data-add-team-criterion]').forEach(function (btn) {
    var nextIndex = parseInt(btn.getAttribute('data-start-index'), 10) || 0;
    btn.addEventListener('click', function () {
        var wrap = document.getElementById(btn.getAttribute('data-add-team-criterion') + '-extras');
        var row = document.createElement('div');
        row.className = 'row g-2 align-items-end mb-2';
        row.innerHTML =
            '<div class="col-md-5"><input type="text" name="team_criteria[' + nextIndex + '][title]" class="form-control form-control-sm"></div>' +
            '<div class="col-md-3"><input type="number" name="team_criteria[' + nextIndex + '][max_points]" class="form-control form-control-sm" min="0.01" step="0.01"></div>';
        wrap.appendChild(row);
        nextIndex++;
    });
});
</script>
@endsection
