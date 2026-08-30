@extends('layouts.app')

@section('title', $project->title)

@section('content')
<div class="container py-4 animate-in">
    <div class="mb-3">
        <a href="{{ route('projects.index') }}" class="small">&larr; {{ __('projects.title') }}</a>
    </div>

    <div class="app-card card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <div class="small text-muted">{{ __('projects.subproject') }}</div>
                    <h1 class="page-title mb-1">{{ $project->title }}</h1>
                    <div class="text-muted small">
                        {{ __('projects.assessment') }}: {{ $assessment->title }}
                        · {{ __('projects.module') }}: {{ $assessment->module->title ?? '—' }}
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-start gap-1">
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
            <div class="mt-2">
                @include('projects.partials.join-countdown', ['assessment' => $assessment])
            </div>
        </div>
    </div>

    @if(($isMember ?? false) || $canManage)
        @if($project->team_announcement)
            <div class="alert alert-info">
                <div class="fw-semibold">{{ __('projects.team_announcement') }}</div>
                <div style="white-space: pre-wrap;">{{ $project->team_announcement }}</div>
            </div>
        @endif
        @if($project->team_workspace_url)
            <div class="mb-3">
                <a href="{{ $project->team_workspace_url }}"
                   class="btn btn-outline-primary btn-sm"
                   target="_blank"
                   rel="noopener noreferrer">
                    {{ __('projects.team_workspace_open') }}
                    <span class="text-muted small">({{ __('projects.workspace_provider_'.$project->workspaceProvider()) }})</span>
                </a>
            </div>
        @endif
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5 fw-bold">{{ __('projects.team_description_label') }}</h2>
                    <p class="small text-muted">{{ __('projects.team_description_student_help') }}</p>
                    <p class="mb-0" style="white-space: pre-wrap;">{{ $project->requirements ?: '—' }}</p>
                </div>
            </div>

            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5 fw-bold">{{ __('projects.phases') }}</h2>
                    @forelse($project->phases as $phase)
                        <div class="border-bottom py-2">
                            <div class="fw-semibold">{{ $phase->title }}</div>
                            @if($phase->deadline)
                                <div class="small text-muted">{{ __('projects.deadline') }}: {{ $phase->deadline->format('Y-m-d H:i') }}</div>
                            @endif
                            @if($phase->description)
                                <div class="small" style="white-space: pre-wrap;">{{ $phase->description }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">—</p>
                    @endforelse
                </div>
            </div>

            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <h2 class="h5 fw-bold mb-0">{{ __('projects.deliverables_checklist') }}</h2>
                        @if(($progress['required'] ?? 0) > 0)
                            <span class="badge {{ ($progress['missing'] ?? 0) === 0 ? 'bg-success' : 'bg-light text-dark border' }}">
                                {{ __('projects.deliverables_progress', [
                                    'submitted' => $progress['required'] - $progress['missing'],
                                    'required' => $progress['required'],
                                ]) }}
                            </span>
                        @endif
                    </div>
                    @forelse($checklist ?? [] as $row)
                        @include('projects.partials.deliverable-card', [
                            'row' => $row,
                            'project' => $project,
                            'isMember' => $isMember ?? false,
                            'canManage' => $canManage ?? false,
                        ])
                    @empty
                        <p class="text-muted mb-0 mt-2">{{ __('projects.no_deliverables') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    @if($membership)
                        @include('projects.partials.grade-status', ['visibility' => $gradeVisibility ?? null])
                        @if(($rubric ?? []) !== [] && ($gradeVisibility['can_view'] ?? false))
                            <div class="mb-3">
                                <div class="small fw-semibold">{{ __('projects.rubric_breakdown') }}</div>
                                <ul class="list-unstyled small mb-0">
                                    @foreach($rubric as $criterion)
                                        <li class="d-flex justify-content-between gap-2 border-bottom py-1">
                                            <span>{{ $criterion['title'] }}</span>
                                            <span class="text-muted">
                                                @if($criterion['points'] === null)
                                                    {{ __('projects.criterion_not_scored') }}
                                                @else
                                                    {{ number_format($criterion['points'], 1) }}/{{ number_format($criterion['max_points'], 1) }}
                                                    · {{ __('projects.criterion_percent', ['percent' => number_format((float) $criterion['percent'], 0)]) }}
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                    <h2 class="h5 fw-bold">{{ __('projects.team_members') }}</h2>
                    <p class="small text-muted">
                        {{ __('projects.seats_of', [
                            'current' => $project->activeMemberships->count(),
                            'max' => $assessment->max_team_size,
                        ]) }}
                        · {{ __('projects.remaining_seats') }}: {{ $project->remainingSeats($assessment) }}
                    </p>
                    <ul class="list-unstyled mb-0">
                        @forelse($project->activeMemberships as $row)
                            @include('projects.partials.roster-member', ['member' => $row->user])
                        @empty
                            <li class="text-muted">{{ __('projects.no_members_yet') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            @if(($isMember ?? false) || $canManage)
                <div class="app-card card shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="h5 fw-bold">{{ __('projects.team_history') }}</h2>
                        <ul class="list-unstyled mb-0 small">
                            @forelse($teamHistory ?? [] as $event)
                                <li class="border-bottom py-2">
                                    <div class="fw-semibold">
                                        {{ __('projects.history_event_'.$event->event, [
                                            'name' => $event->user?->displayName() ?? '—',
                                        ]) }}
                                    </div>
                                    <div class="text-muted">
                                        {{ $event->occurred_at?->format('Y-m-d H:i') }}
                                    </div>
                                </li>
                            @empty
                                <li class="text-muted">{{ __('projects.team_history_empty') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endif

            @if(($isMember ?? false) && ($peerEvalOpen ?? false))
                <div class="app-card card shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="h5 fw-bold">{{ __('projects.peer_eval_title') }}</h2>
                        <p class="small text-muted">{{ __('projects.peer_eval_anonymous_hint') }}</p>
                        @if($assessment->peer_eval_prompt)
                            <p class="small" style="white-space: pre-wrap;">{{ $assessment->peer_eval_prompt }}</p>
                        @endif
                        @if($peerProgress)
                            <p class="small mb-2">
                                {{ __('projects.peer_eval_progress', [
                                    'rated' => $peerProgress['rated'],
                                    'min' => $peerProgress['min'],
                                    'max' => $peerProgress['max'],
                                ]) }}
                                @if($peerProgress['complete'])
                                    <span class="badge bg-success">{{ __('projects.peer_eval_complete') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ __('projects.peer_eval_incomplete') }}</span>
                                @endif
                            </p>
                        @endif
                        @if(($peerEligible ?? collect())->isEmpty())
                            <p class="text-muted mb-0 small">{{ __('projects.peer_eval_no_eligible_teams') }}</p>
                        @else
                            <form method="POST" action="{{ route('projects.peer-ratings.store', $project) }}">
                                @csrf
                                @foreach($peerEligible as $index => $ratee)
                                    @php
                                        $existing = ($peerRatings ?? collect())->get((int) $ratee->project_id);
                                    @endphp
                                    <div class="border rounded p-2 mb-2">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                                            <div class="fw-semibold small">{{ $ratee->title }}</div>
                                            <a class="small" href="{{ route('projects.peer-review', $ratee) }}" target="_blank" rel="noopener">
                                                {{ __('projects.peer_eval_review_work') }}
                                            </a>
                                        </div>
                                        <input type="hidden" name="ratings[{{ $index }}][ratee_project_id]" value="{{ $ratee->project_id }}">
                                        <label class="form-label small">{{ __('projects.peer_eval_score') }} (1–{{ (int) ($assessment->peer_eval_scale_max ?: 5) }})</label>
                                        <input type="number" name="ratings[{{ $index }}][score]" class="form-control form-control-sm mb-1"
                                               min="1" max="{{ (int) ($assessment->peer_eval_scale_max ?: 5) }}"
                                               value="{{ old('ratings.'.$index.'.score', $existing?->score) }}"
                                               placeholder="{{ __('projects.peer_eval_score_optional') }}">
                                        <label class="form-label small">{{ __('projects.peer_eval_comment') }}</label>
                                        <textarea name="ratings[{{ $index }}][comment]" class="form-control form-control-sm" rows="2">{{ old('ratings.'.$index.'.comment', $existing?->comment) }}</textarea>
                                    </div>
                                @endforeach
                                <p class="small text-muted">{{ __('projects.peer_eval_self_pick_help', ['max' => $peerProgress['max'] ?? 3]) }}</p>
                                @error('ratings')
                                    <div class="alert alert-danger py-2 small">{{ $message }}</div>
                                @enderror
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('projects.peer_eval_submit') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            @if($canManage && ($peerTeamAverages ?? []) !== [])
                <div class="app-card card shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="h5 fw-bold">{{ __('projects.peer_eval_admin_title') }}</h2>
                        <p class="small text-muted">{{ __('projects.peer_eval_admin_hint') }}</p>
                        @foreach($peerTeamAverages as $row)
                            @if((int) $row['project_id'] !== (int) $project->project_id)
                                @continue
                            @endif
                            <div class="small mb-1">
                                <span class="fw-semibold">{{ __('projects.peer_eval_overall') }}:</span>
                                @if($row['overall_avg'] === null)
                                    —
                                @else
                                    {{ number_format($row['overall_avg'], 2) }}
                                    ({{ __('projects.peer_eval_ratings_count', ['count' => $row['ratings_count']]) }})
                                @endif
                            </div>
                            @foreach($row['by_rater_team'] as $from)
                                <div class="small text-muted">
                                    {{ __('projects.peer_eval_from_team', ['team' => $from['title']]) }}:
                                    {{ number_format($from['average'], 2) }}
                                    ({{ __('projects.peer_eval_ratings_count', ['count' => $from['ratings_count']]) }})
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif

            @if($membership && ! $canManage)
                <div class="app-card card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 fw-bold">{{ __('projects.leave_team') }}</h2>
                        @if($changeUsed)
                            <p class="text-muted mb-0">{{ __('projects.change_chance_used') }}</p>
                        @elseif(! $joinWindowOpen)
                            <p class="text-muted mb-0">{{ __('projects.join_window_closed') }}</p>
                        @else
                            <p class="small text-muted">{{ __('projects.leave_team_help') }}</p>
                            @error('project')
                                <div class="alert alert-danger py-2 small">{{ $message }}</div>
                            @enderror
                            <form method="POST"
                                  action="{{ route('projects.leave', $assessment) }}"
                                  onsubmit="return confirm(@json(__('projects.leave_confirm')));">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">{{ __('projects.leave_submit') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@include('projects.partials.countdown-script')
@endsection
