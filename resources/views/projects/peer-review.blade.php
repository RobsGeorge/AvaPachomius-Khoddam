@extends('layouts.app')

@section('title', __('projects.peer_eval_review_title', ['title' => $project->title]))

@section('content')
<div class="container py-4 animate-in">
    <div class="mb-3">
        <a href="javascript:history.back()" class="small">&larr; {{ __('projects.peer_eval_back') }}</a>
    </div>

    <div class="app-card card shadow-sm mb-3">
        <div class="card-body">
            <div class="small text-muted">{{ __('projects.peer_eval_review_heading') }}</div>
            <h1 class="page-title mb-1">{{ $project->title }}</h1>
            <div class="text-muted small">
                {{ __('projects.assessment') }}: {{ $assessment->title }}
                · {{ __('projects.module') }}: {{ $assessment->module->title ?? '—' }}
            </div>
            <p class="small text-muted mt-2 mb-0">{{ __('projects.peer_eval_review_hint') }}</p>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5 fw-bold">{{ __('projects.requirements') }}</h2>
            <p class="mb-0" style="white-space: pre-wrap;">{{ $project->requirements ?: '—' }}</p>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5 fw-bold">{{ __('projects.peer_eval_submitted_work') }}</h2>
            @forelse($checklist as $row)
                @php
                    $deliverable = $row['deliverable'];
                    $submission = $row['submission'];
                @endphp
                <div class="border-bottom py-3">
                    <div class="fw-semibold">
                        {{ $deliverable->title }}
                        <span class="badge bg-light text-dark border">{{ __('projects.submission_type_'.$deliverable->type()) }}</span>
                        <span class="badge bg-success">{{ __('projects.submitted') }}</span>
                    </div>
                    @if($deliverable->description)
                        <div class="small mt-2" style="white-space: pre-wrap;">{{ $deliverable->description }}</div>
                    @endif
                    @if($submission)
                        <div class="mt-2 small">
                            @if($submission->link_url)
                                <div class="mt-1">
                                    <a href="{{ $submission->link_url }}" target="_blank" rel="noopener noreferrer">{{ $submission->link_url }}</a>
                                </div>
                            @endif
                            @if($submission->body)
                                <div class="mt-1" style="white-space: pre-wrap;">{{ $submission->body }}</div>
                            @endif
                            @if($submission->files->isNotEmpty())
                                <ul class="list-unstyled mb-0 mt-1">
                                    @foreach($submission->files as $file)
                                        <li>
                                            <a href="{{ $file->url() }}" target="_blank" rel="noopener noreferrer">{{ $file->displayName() }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('projects.peer_eval_no_submitted_work') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
