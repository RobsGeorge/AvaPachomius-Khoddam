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
                <span class="badge {{ $project->isClosed() ? 'bg-success' : 'bg-info text-dark' }}">
                    {{ $project->isClosed() ? __('projects.team_closed') : __('projects.team_open') }}
                </span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5 fw-bold">{{ __('projects.requirements') }}</h2>
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
                    <h2 class="h5 fw-bold">{{ __('projects.deliverables') }}</h2>
                    @forelse($project->deliverables as $deliverable)
                        <div class="border-bottom py-2">
                            <div class="fw-semibold">{{ $deliverable->title }}</div>
                            @if($deliverable->due_at)
                                <div class="small text-muted">{{ __('projects.due_at') }}: {{ $deliverable->due_at->format('Y-m-d H:i') }}</div>
                            @endif
                            @if($deliverable->description)
                                <div class="small" style="white-space: pre-wrap;">{{ $deliverable->description }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">—</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    @if($membership)
                        @include('projects.partials.grade-status', ['visibility' => $gradeVisibility ?? null])
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
                            <li class="mb-2">
                                <div class="fw-semibold">{{ $row->user?->displayName() }}</div>
                                <div class="small text-muted">{{ $row->user?->mobile_number ?: __('projects.phone_missing') }}</div>
                            </li>
                        @empty
                            <li class="text-muted">{{ __('projects.no_members_yet') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            @if($membership && ! $canManage)
                <div class="app-card card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 fw-bold">{{ __('projects.change_team') }}</h2>
                        @if($changeUsed)
                            <p class="text-muted mb-0">{{ __('projects.change_chance_used') }}</p>
                        @elseif($pendingChange)
                            <p class="text-muted mb-0">{{ __('projects.change_pending') }}</p>
                        @else
                            <p class="small text-muted">{{ __('projects.change_reason_help') }}</p>
                            <form method="POST" action="{{ route('projects.change-requests.store', $assessment) }}">
                                @csrf
                                <label class="form-label" for="reason">{{ __('projects.change_reason') }}</label>
                                <textarea name="reason" id="reason" class="form-control mb-2 @error('reason') is-invalid @enderror" rows="3" required>{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <button type="submit" class="btn btn-outline-theme">{{ __('projects.change_submit') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
