@extends('layouts.app')

@section('title', __('projects.title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('projects.title') }}</h1>
        @if($canManage)
            <a href="{{ route('projects.manage') }}" class="btn btn-primary">
                <i class="bi bi-gear"></i> {{ __('projects.manage') }}
            </a>
        @endif
    </div>

    <div class="alert alert-light border mb-4">
        <div class="fw-semibold mb-1">{{ __('projects.onboarding_student_title') }}</div>
        <p class="mb-2 small">{{ __('projects.onboarding_student_intro') }}</p>
        <ol class="small mb-0 ps-3">
            <li>{{ __('projects.onboarding_student_step_1') }}</li>
            <li>{{ __('projects.onboarding_student_step_2') }}</li>
            <li>{{ __('projects.onboarding_student_step_3') }}</li>
        </ol>
    </div>

    @if($assessments->isEmpty())
        <div class="app-card card shadow-sm">
            <div class="card-body text-muted">{{ __('projects.empty') }}</div>
        </div>
    @endif

    <div class="row g-3">
        @foreach($assessments as $assessment)
            @php
                $membership = $memberships[$assessment->project_assessment_id] ?? null;
                $assignedProject = $membership?->project;
            @endphp
            <div class="col-12">
                <div class="app-card card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <div>
                                <h2 class="h5 fw-bold mb-1">{{ $assessment->title }}</h2>
                                <div class="text-muted small">
                                    {{ __('projects.module') }}: {{ $assessment->module->title ?? '—' }}
                                </div>
                            </div>
                            @if(! $assessment->is_published)
                                <span class="badge bg-secondary">{{ __('projects.draft_badge') }}</span>
                            @endif
                        </div>

                        @include('projects.partials.join-countdown', ['assessment' => $assessment])

                        @if($membership && $assignedProject)
                            <p class="mb-3">{{ __('projects.assigned_to', ['title' => $assignedProject->title]) }}</p>
                            @include('projects.partials.grade-status', ['visibility' => $gradeVisibility[$assessment->project_assessment_id] ?? null])
                            <a href="{{ route('projects.show', $assignedProject) }}" class="btn btn-outline-primary">
                                {{ __('projects.open_project') }}
                            </a>
                        @elseif($assessment->is_published && $assessment->isJoinWindowOpen())
                            <form method="POST" action="{{ route('projects.join', $assessment) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary">{{ __('projects.get_assigned') }}</button>
                            </form>
                        @elseif($assessment->is_published)
                            <p class="text-muted mb-0">{{ __('projects.join_window_closed') }}</p>
                        @else
                            <p class="text-muted mb-0">{{ __('projects.unpublished') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@include('projects.partials.countdown-script')
@endsection
