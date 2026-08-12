@extends('layouts.app')

@section('title', __('service.edit_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:920px;">
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> {{ __('service.manage_title') }}
        </a>
        <h1 class="page-title mb-0">{{ $service->localizedTitle() }}</h1>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="app-card card shadow-sm">
                <div class="card-header fw-semibold">{{ __('service.edit_title') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.services.update', $service) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('service.field_title') }}</label>
                            <input type="text" name="title" class="form-control form-control-sm @error('title') is-invalid @enderror"
                                   value="{{ old('title', $service->title) }}" maxlength="120" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">{{ __('service.field_title_ar') }}</label>
                                <input type="text" name="title_ar" class="form-control form-control-sm" value="{{ old('title_ar', $service->title_ar) }}" maxlength="120">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">{{ __('service.field_title_en') }}</label>
                                <input type="text" name="title_en" class="form-control form-control-sm" value="{{ old('title_en', $service->title_en) }}" maxlength="120">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('pages.description') }}</label>
                            <textarea name="description" rows="3" class="form-control form-control-sm" maxlength="2000">{{ old('description', $service->description) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('events.status') }}</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" @selected(old('status', $service->status) === 'active')>{{ __('service.status_active') }}</option>
                                <option value="archived" @selected(old('status', $service->status) === 'archived')>{{ __('service.status_archived') }}</option>
                            </select>
                        </div>
                        @if(\Illuminate\Support\Facades\Schema::hasColumn('service', 'structure_template_id'))
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">{{ __('service.field_structure_template') }}</label>
                                <select name="structure_template_id" class="form-select form-select-sm @error('structure_template_id') is-invalid @enderror" required>
                                    @foreach($structureTemplates as $template)
                                        <option value="{{ $template->structure_template_id }}"
                                            @selected((string) old('structure_template_id', $service->structure_template_id) === (string) $template->structure_template_id)>
                                            {{ $template->localizedName() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('structure_template_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endif
                        @if(\Illuminate\Support\Facades\Schema::hasColumn('service', 'progression_policy'))
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">{{ __('service.field_progression_policy') }}</label>
                                <select name="progression_policy" class="form-select form-select-sm">
                                    <option value="">{{ __('service.progression_inherit_template') }}
                                        @if($resolver->progressionPolicy($service) && ! $service->progression_policy)
                                            ({{ __('service.progression_'.$resolver->progressionPolicy($service)) }})
                                        @endif
                                    </option>
                                    @foreach($progressionPolicies as $policy)
                                        <option value="{{ $policy }}" @selected(old('progression_policy', $service->progression_policy) === $policy)>
                                            {{ __('service.progression_'.$policy) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text small">{{ __('service.progression_policy_hint') }}</div>
                            </div>
                        @endif
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('pages.save') }}</button>
                    </form>
                </div>
            </div>

            @if($resolver->supportsEndOfCycleWizard($service))
                <div class="app-card card shadow-sm mt-3 border-primary">
                    <div class="card-header fw-semibold">{{ __('service.cycle_title') }}</div>
                    <div class="card-body">
                        <p class="text-muted-theme small mb-3">{{ __('service.cycle_edit_hint') }}</p>
                        <a href="{{ route('admin.services.cycle.show', $service) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-diagram-3"></i> {{ __('service.cycle_open') }}
                        </a>
                    </div>
                </div>
            @endif

            <div class="app-card card shadow-sm mt-3">
                <div class="card-header fw-semibold">{{ __('rbac.section_service') }}</div>
                <div class="card-body">
                    <p class="text-muted-theme small mb-3">{{ __('service.roles_panel_hint') }}</p>
                    <a href="{{ app(\App\Services\RolesHubService::class)->hubUrl(null, 'service', $service) }}" class="btn btn-outline-theme btn-sm">
                        <i class="bi bi-shield-check"></i> {{ __('service.open_roles') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="app-card card shadow-sm">
                <div class="card-header fw-semibold">{{ __('service.linked_courses') }}</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($service->courses()->orderByDesc('year')->orderBy('title')->get() as $course)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $course->localizedTitle() }} <span class="text-muted-theme small">({{ $course->year }})</span></span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted-theme">{{ __('service.no_linked_courses') }}</li>
                        @endforelse
                    </ul>
                </div>
                <div class="card-footer">
                    <form method="POST" action="{{ route('admin.services.link-course', $service) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col">
                            <label class="form-label small fw-semibold mb-1">{{ __('service.link_course') }}</label>
                            <select name="course_id" class="form-select form-select-sm" required>
                                <option value="">{{ __('service.choose_course') }}</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->course_id }}" @selected((int) $course->service_id === (int) $service->service_id)>
                                        {{ $course->localizedTitle() }} ({{ $course->year }})
                                        @if($course->service_id && (int) $course->service_id !== (int) $service->service_id)
                                            — {{ __('service.currently_other') }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('service.link_course') }}</button>
                        </div>
                    </form>
                    @error('course_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
