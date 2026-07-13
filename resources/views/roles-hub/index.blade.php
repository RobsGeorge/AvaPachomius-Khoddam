@extends('layouts.app')

@section('title', __('rbac.hub_title'))

@section('content')
<div class="container py-3 animate-in roles-hub">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1 h4">{{ __('rbac.hub_title') }}</h1>
            <p class="text-muted-theme small mb-0">{{ __('rbac.hub_intro') }}</p>
        </div>
        @if($manageableCourses->isNotEmpty())
            <form method="GET" action="{{ route('roles.hub') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="hidden" name="section" value="{{ $section }}">
                <label for="hub-course" class="small text-muted-theme mb-0">{{ __('pages.course') }}</label>
                <select name="course" id="hub-course" class="form-select form-select-sm" style="min-width: 12rem;" onchange="this.form.submit()">
                    @foreach($manageableCourses as $c)
                        <option value="{{ $c->course_id }}" @selected($course && (int) $course->course_id === (int) $c->course_id)>
                            {{ $c->title }}@if($c->year) ({{ $c->year }})@endif
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning py-2 small">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2 small">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning py-2 small">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2 small">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2 small">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="accordion accordion-flush roles-hub-accordion" id="rolesHubAccordion">
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
    </div>
</div>
@endsection

@push('styles')
<style>
.roles-hub-accordion .accordion-button { font-size: 0.95rem; }
.roles-hub-accordion .accordion-button:not(.collapsed) { background: var(--bs-light, #f8f9fa); }
.roles-hub-panel, .roles-hub-subpanel { border: 1px solid var(--border-subtle, #dee2e6); border-radius: 0.375rem; padding: 0.35rem 0.65rem; background: var(--card-bg, #fff); }
.roles-hub-summary, .roles-hub-subsummary { cursor: pointer; font-weight: 600; font-size: 0.875rem; list-style: none; }
.roles-hub-summary::-webkit-details-marker, .roles-hub-subsummary::-webkit-details-marker { display: none; }
.roles-hub-subpanel { margin-inline-start: 0.5rem; background: transparent; }
</style>
@endpush

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
