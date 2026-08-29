@php
    $teamRows = \App\Models\ProjectTeamGradeCriterion::query()
        ->where('project_id', $project->project_id)
        ->orderBy('sort_order')
        ->get();
    $overrides = $teamRows->filter(fn ($row) => $row->isOverride())->keyBy('project_grade_criterion_id');
    $extras = $teamRows->reject(fn ($row) => $row->isOverride())->values();
    $formPrefix = 'team-rubric-'.$project->project_id;
    $effectiveTotal = array_sum(array_column($effectiveCriteria, 'max_points'));
@endphp
<details class="border rounded p-3 mb-4" @if($isCustom) open @endif>
    <summary class="fw-semibold">
        {{ __('projects.team_rubric') }}
        <span class="badge {{ abs($effectiveTotal - (float) $maxPoints) < 0.01 ? 'bg-light text-dark border' : 'bg-danger' }}">
            {{ __('projects.team_rubric_total', [
                'total' => number_format($effectiveTotal, 1),
                'max' => number_format((float) $maxPoints, 1),
            ]) }}
        </span>
    </summary>

    <p class="small text-muted mt-2">
        {{ __('projects.team_rubric_hint', ['max' => number_format((float) $maxPoints, 1)]) }}
    </p>

    @error('team_criteria')
        <div class="alert alert-danger py-2 small">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('projects.grades.team-criteria', $project) }}">
        @csrf
        @method('PUT')

        @foreach($criteria as $index => $criterion)
            @php
                $override = $overrides->get($criterion->project_grade_criterion_id);
                $rowId = $formPrefix.'-shared-'.$criterion->project_grade_criterion_id;
            @endphp
            <div class="row g-2 align-items-center mb-2">
                <input type="hidden" name="team_criteria[{{ $index }}][project_grade_criterion_id]" value="{{ $criterion->project_grade_criterion_id }}">
                <div class="col-md-5">
                    <label class="form-label small mb-1" for="{{ $rowId }}-title">
                        {{ __('projects.team_rubric_shared') }}: {{ $criterion->title }}
                    </label>
                    <input type="text"
                           id="{{ $rowId }}-title"
                           name="team_criteria[{{ $index }}][title]"
                           class="form-control form-control-sm"
                           value="{{ $override?->title }}"
                           placeholder="{{ $criterion->title }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="{{ $rowId }}-max">{{ __('projects.criterion_max') }}</label>
                    <input type="number"
                           id="{{ $rowId }}-max"
                           name="team_criteria[{{ $index }}][max_points]"
                           class="form-control form-control-sm"
                           min="0" step="0.01"
                           value="{{ number_format((float) ($override && ! $override->is_excluded ? $override->max_points : $criterion->max_points), 2, '.', '') }}">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-1">
                        <input type="hidden" name="team_criteria[{{ $index }}][is_excluded]" value="0">
                        <input class="form-check-input" type="checkbox" value="1"
                               name="team_criteria[{{ $index }}][is_excluded]"
                               id="{{ $rowId }}-exclude"
                               @checked($override?->is_excluded)>
                        <label class="form-check-label small" for="{{ $rowId }}-exclude">{{ __('projects.team_rubric_exclude') }}</label>
                    </div>
                </div>
            </div>
        @endforeach

        <div id="{{ $formPrefix }}-extras">
            @foreach($extras as $extraIndex => $extra)
                @php $index = $criteria->count() + $extraIndex; @endphp
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-5">
                        <input type="text"
                               name="team_criteria[{{ $index }}][title]"
                               class="form-control form-control-sm"
                               value="{{ $extra->title }}"
                               placeholder="{{ __('projects.criterion_title') }}">
                    </div>
                    <div class="col-md-3">
                        <input type="number"
                               name="team_criteria[{{ $index }}][max_points]"
                               class="form-control form-control-sm"
                               min="0.01" step="0.01"
                               value="{{ number_format((float) $extra->max_points, 2, '.', '') }}">
                    </div>
                    <div class="col-md-4 small text-muted">{{ __('projects.team_rubric_custom_badge') }}</div>
                </div>
            @endforeach
        </div>

        <button type="button"
                class="btn btn-sm btn-outline-secondary mb-3"
                data-add-team-criterion="{{ $formPrefix }}"
                data-start-index="{{ $criteria->count() + $extras->count() }}">
            {{ __('projects.team_rubric_add_criterion') }}
        </button>

        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-primary">{{ __('projects.team_rubric_save') }}</button>
        </div>
    </form>

    @if($isCustom)
        <form method="POST" action="{{ route('projects.grades.team-criteria.reset', $project) }}" class="mt-2">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-outline-danger">{{ __('projects.team_rubric_reset') }}</button>
        </form>
    @endif
</details>
