@extends('layouts.app')

@section('title', __('rbac.hub_title'))

@section('content')
<div class="container py-3 animate-in roles-hub">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1 h4">{{ __('rbac.hub_title') }}</h1>
            <p class="text-muted-theme small mb-0">{{ __('rbac.hub_intro') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            @if(($manageableServices ?? collect())->isNotEmpty())
                @if(($manageableServices->count() > 1))
                    <form method="GET" action="{{ route('roles.hub') }}" class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="hidden" name="section" value="{{ $section }}">
                        @if($course)<input type="hidden" name="course" value="{{ $course->course_id }}">@endif
                        <label for="hub-service-top" class="small text-muted-theme mb-0">{{ __('service.label') }}</label>
                        <select name="service" id="hub-service-top" class="form-select form-select-sm" style="min-width: 12rem;" onchange="this.form.submit()">
                            @foreach($manageableServices as $s)
                                <option value="{{ $s->service_id }}" @selected(($service->service_id ?? null) == $s->service_id)>
                                    {{ $s->localizedTitle() }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @elseif($service ?? null)
                    <span class="badge bg-primary-subtle text-primary-emphasis border">
                        <i class="bi bi-building me-1"></i>{{ $service->localizedTitle() }}
                    </span>
                @endif
            @endif
            @if($manageableCourses->isNotEmpty())
                @if($manageableCourses->count() > 1)
                    <form method="GET" action="{{ route('roles.hub') }}" class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="hidden" name="section" value="{{ $section }}">
                        @if($service)<input type="hidden" name="service" value="{{ $service->service_id }}">@endif
                        <label for="hub-course" class="small text-muted-theme mb-0">{{ __('pages.course') }}</label>
                        <select name="course" id="hub-course" class="form-select form-select-sm" style="min-width: 12rem;" onchange="this.form.submit()">
                            @foreach($manageableCourses as $c)
                                <option value="{{ $c->course_id }}" @selected($course && (int) $course->course_id === (int) $c->course_id)>
                                    {{ $c->title }}@if($c->year) ({{ $c->year }})@endif
                                </option>
                            @endforeach
                        </select>
                    </form>
                @elseif($course)
                    <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                        <i class="bi bi-mortarboard me-1"></i>{{ $course->localizedTitle() }}
                    </span>
                @endif
            @endif
        </div>
    </div>

<div class="accordion accordion-flush roles-hub-accordion" id="rolesHubAccordion">
        @if(in_array('service', $visibleSections, true))
            @include('roles-hub.partials.section-service')
        @endif
        @if(in_array('course', $visibleSections, true))
            @include('roles-hub.partials.section-course')
        @endif
        @if(in_array('assignments', $visibleSections, true))
            @include('roles-hub.partials.section-assignments')
        @endif
        @if(in_array('templates', $visibleSections, true))
            @include('roles-hub.partials.section-templates')
        @endif
        @if(in_array('system', $visibleSections, true))
            @include('roles-hub.partials.section-system')
        @endif
        @if(in_array('visibility', $visibleSections, true))
            @include('roles-hub.partials.section-visibility')
        @endif
        @if(in_array('email-templates', $visibleSections, true))
            @include('roles-hub.partials.section-email-templates')
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const openId = @json('section-'.$section);
    const el = document.getElementById(openId);
    if (el) {
        bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
    }
});
</script>
@endpush
