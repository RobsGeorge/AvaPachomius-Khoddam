@extends('layouts.app')

@section('title', __('service.cycle_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:1100px;">
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        <a href="{{ route('admin.services.edit', $service) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> {{ __('service.edit_title') }}
        </a>
        <h1 class="page-title mb-0">{{ __('service.cycle_title') }}</h1>
    </div>

    <p class="text-muted-theme small mb-3">
        {{ __('service.cycle_intro', ['service' => $service->localizedTitle(), 'policy' => __('service.progression_'.$proposal['policy'])]) }}
    </p>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted-theme small">{{ __('service.cycle_count_eligible') }}</div>
                    <div class="fs-4 fw-semibold">{{ $proposal['counts']['eligible'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted-theme small">{{ __('service.cycle_count_ready') }}</div>
                    <div class="fs-4 fw-semibold text-success">{{ $proposal['counts']['ready'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted-theme small">{{ __('service.cycle_count_blocked') }}</div>
                    <div class="fs-4 fw-semibold text-warning">{{ $proposal['counts']['blocked'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('service.cycle_ladder_edges') }}</div>
        <div class="card-body">
            <p class="text-muted-theme small">{{ __('service.cycle_ladder_hint') }}</p>
            <form method="POST" action="{{ route('admin.services.cycle.edges', $service) }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="cycle-edges-table">
                        <thead>
                            <tr>
                                <th>{{ __('service.cycle_from_course') }}</th>
                                <th>{{ __('service.cycle_to_course') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $edgeRows = old('edges', $proposal['edges']); @endphp
                            @forelse($edgeRows as $i => $edge)
                                <tr>
                                    <td>
                                        <select name="edges[{{ $i }}][from_course_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($courses as $course)
                                                <option value="{{ $course->course_id }}" @selected((int)($edge['from_course_id'] ?? 0) === (int)$course->course_id)>
                                                    {{ $course->localizedTitle() }} ({{ $course->year }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="edges[{{ $i }}][to_course_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($courses as $course)
                                                <option value="{{ $course->course_id }}" @selected((int)($edge['to_course_id'] ?? 0) === (int)$course->course_id)>
                                                    {{ $course->localizedTitle() }} ({{ $course->year }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                @for($i = 0; $i < 3; $i++)
                                    <tr>
                                        <td>
                                            <select name="edges[{{ $i }}][from_course_id]" class="form-select form-select-sm">
                                                <option value="">—</option>
                                                @foreach($courses as $course)
                                                    <option value="{{ $course->course_id }}">{{ $course->localizedTitle() }} ({{ $course->year }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select name="edges[{{ $i }}][to_course_id]" class="form-select form-select-sm">
                                                <option value="">—</option>
                                                @foreach($courses as $course)
                                                    <option value="{{ $course->course_id }}">{{ $course->localizedTitle() }} ({{ $course->year }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endfor
                            @endforelse
                            @if(count($edgeRows) > 0)
                                @php $extra = count($edgeRows); @endphp
                                <tr>
                                    <td>
                                        <select name="edges[{{ $extra }}][from_course_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($courses as $course)
                                                <option value="{{ $course->course_id }}">{{ $course->localizedTitle() }} ({{ $course->year }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="edges[{{ $extra }}][to_course_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($courses as $course)
                                                <option value="{{ $course->course_id }}">{{ $course->localizedTitle() }} ({{ $course->year }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('service.cycle_save_edges') }}</button>
            </form>
        </div>
    </div>

    <div class="app-card card shadow-sm">
        <div class="card-header fw-semibold">{{ __('service.cycle_proposal') }}</div>
        <div class="card-body p-0">
            @if(empty($proposal['rows']))
                <p class="text-muted-theme p-3 mb-0">{{ __('service.cycle_no_eligible') }}</p>
            @else
                <form method="POST" action="{{ route('admin.services.cycle.confirm', $service) }}"
                      data-confirm="{{ __('service.cycle_confirm_apply') }}"
                      onsubmit="return confirm(this.dataset.confirm)">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('service.cycle_person') }}</th>
                                    <th>{{ __('service.cycle_from_course') }}</th>
                                    <th>{{ __('service.cycle_action') }}</th>
                                    <th>{{ __('service.cycle_to_course') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($proposal['rows'] as $i => $row)
                                    <tr @class(['table-warning' => $row['block_reason']])>
                                        <td>
                                            <div class="fw-semibold">
                                                {{ $row['user_name'] }}
                                                @if($row['placement_id'])
                                                    <span class="badge bg-secondary-subtle text-secondary border">{{ __('service.cycle_people_only_badge') }}</span>
                                                @endif
                                            </div>
                                            @if($row['block_reason'] === 'missing_edge')
                                                <div class="small text-warning">{{ __('service.cycle_blocked_missing_edge') }}</div>
                                            @endif
                                            @if($row['enrollment_id'])
                                                <input type="hidden" name="decisions[{{ $i }}][enrollment_id]" value="{{ $row['enrollment_id'] }}">
                                            @else
                                                <input type="hidden" name="decisions[{{ $i }}][placement_id]" value="{{ $row['placement_id'] }}">
                                            @endif
                                        </td>
                                        <td class="small">{{ $row['from_course_title'] }}</td>
                                        <td>
                                            <select name="decisions[{{ $i }}][action]" class="form-select form-select-sm">
                                                <option value="promote" @selected($row['suggested_action'] === 'promote')>{{ __('service.cycle_action_promote') }}</option>
                                                <option value="skip" @selected($row['suggested_action'] === 'skip')>{{ __('service.cycle_action_skip') }}</option>
                                                <option value="mark_inactive">{{ __('service.cycle_action_inactive') }}</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="decisions[{{ $i }}][to_course_id]" class="form-select form-select-sm">
                                                <option value="">—</option>
                                                @foreach($courses as $course)
                                                    <option value="{{ $course->course_id }}" @selected((int)($row['to_course_id'] ?? 0) === (int)$course->course_id)>
                                                        {{ $course->localizedTitle() }} ({{ $course->year }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('service.cycle_apply') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
