@php
    $showHeading = $showHeading ?? true;
    $showTeamTitle = $showTeamTitle ?? false;
    $fallbackRequirements = $fallbackRequirements ?? true;
@endphp
@if($showHeading)
    <h2 class="h5 fw-bold">{{ __('projects.team_description_label') }}</h2>
    <p class="small text-muted">{{ __('projects.team_description_student_help') }}</p>
@endif
@if($showTeamTitle)
    <div class="mb-2">
        <div class="small text-muted">{{ __('projects.subproject_title') }}</div>
        <div class="fw-semibold">{{ $project->title }}</div>
    </div>
@endif
<dl class="row mb-0">
    @foreach(\App\Models\Project::BRIEF_KEYS as $key)
        <dt class="col-sm-4 col-lg-3">{{ __('projects.'.$key) }}</dt>
        <dd class="col-sm-8 col-lg-9" style="white-space: pre-wrap;">{{ $project->{$key} ?: '—' }}</dd>
    @endforeach
</dl>
@if($fallbackRequirements && ! $project->hasStructuredBrief() && $project->requirements)
    <p class="small mt-2 mb-0" style="white-space: pre-wrap;">{{ $project->requirements }}</p>
@elseif($project->hasStructuredBrief() && $project->leftoverLegacyRequirements())
    @include('projects.partials.team-brief-legacy', [
        'text' => $project->leftoverLegacyRequirements(),
    ])
@endif
