@extends('layouts.app')

@section('title', __('projects.report_title'))

@section('content')
<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('projects.manage') }}" class="small">&larr; {{ __('projects.manage_title') }}</a>
    </div>

    <div class="app-card card shadow-sm mb-3">
        <div class="card-body">
            <h1 class="page-title mb-1">{{ __('projects.report_title') }}</h1>
            <div class="text-muted small">
                {{ $assessment->title }}
                · {{ __('projects.module') }}: {{ $assessment->module->title ?? '—' }}
            </div>
            @if($assessment->areTeamsSettled())
                <p class="small mt-2 mb-0">
                    {{ __('projects.roster_settled_at', [
                        'when' => $assessment->teams_settled_at?->format('Y-m-d H:i') ?? '—',
                    ]) }}
                </p>
            @endif
        </div>
    </div>

    @foreach($rows as $row)
        @php $project = $row['project']; @endphp
        <div class="app-card card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h2 class="h5 fw-bold mb-1">{{ $project->title }}</h2>
                        <div class="small text-muted">
                            {{ __('projects.seats_of', [
                                'current' => $project->activeMemberships->count(),
                                'max' => $assessment->max_team_size,
                            ]) }}
                        </div>
                    </div>
                    <div>
                        @if($project->isFinalSubmitted())
                            <span class="badge bg-success">{{ __('projects.final_submitted_badge') }}</span>
                            @if($project->isLateFinal())
                                <span class="badge bg-warning text-dark">{{ __('projects.late') }}</span>
                            @endif
                        @endif
                    </div>
                </div>

                @if($project->isFinalSubmitted())
                    <p class="small mt-2">
                        {{ __('projects.final_submitted_by', [
                            'name' => $project->finalSubmitter?->displayName() ?? '—',
                            'when' => $project->final_submitted_at?->format('Y-m-d H:i') ?? '—',
                        ]) }}
                    </p>
                @else
                    <p class="small text-muted mt-2">{{ __('projects.report_not_submitted') }}</p>
                @endif

                <h3 class="h6 fw-semibold mt-3">{{ __('projects.deliverables_checklist') }}</h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('projects.deliverable') }}</th>
                                <th>{{ __('projects.submitted') }}</th>
                                <th>{{ __('projects.report_submitted_by') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($row['checklist'] as $item)
                                @php $submission = $item['submission']; @endphp
                                <tr>
                                    <td>
                                        {{ $item['deliverable']->title }}
                                        @if($item['late'])
                                            <span class="badge bg-warning text-dark">{{ __('projects.late') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item['submitted'])
                                            {{ $submission?->submitted_at?->format('Y-m-d H:i') }}
                                        @else
                                            {{ __('projects.not_submitted') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($submission)
                                            {{ $submission->submitter?->displayName() ?? '—' }}
                                            @if($submission->link_url)
                                                <div class="small">
                                                    <a href="{{ $submission->link_url }}" target="_blank" rel="noopener noreferrer">{{ $submission->link_url }}</a>
                                                </div>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3 class="h6 fw-semibold mt-3">{{ __('projects.verify_heading') }}</h3>
                <ul class="list-unstyled small mb-0">
                    @foreach($project->activeMemberships as $membership)
                        @php
                            $verification = $row['verifications']->firstWhere('user_id', $membership->user_id);
                        @endphp
                        <li class="d-flex justify-content-between gap-2 border-bottom py-1">
                            <span>{{ $membership->user?->displayName() }}</span>
                            @if($verification)
                                <span>
                                    {{ __('projects.report_verified_by') }}
                                    · {{ $verification->verified_at?->format('Y-m-d H:i') }}
                                </span>
                            @else
                                <span class="text-muted">{{ __('projects.report_not_verified') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endforeach
</div>
@endsection
