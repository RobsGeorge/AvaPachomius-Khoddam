@php $open = $section === 'visibility'; @endphp
<div class="accordion-item app-card card shadow-sm mb-2 border-0">
    <h2 class="accordion-header">
        <button class="accordion-button {{ $open ? '' : 'collapsed' }} py-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#section-visibility">
            <i class="bi bi-eye me-2"></i>
            <span class="fw-semibold">{{ __('rbac.group_visibility') }}</span>
        </button>
    </h2>
    <div id="section-visibility" class="accordion-collapse collapse {{ $open ? 'show' : '' }}" data-bs-parent="#rolesHubAccordion">
        <div class="accordion-body py-2 px-3">
            <p class="small text-muted-theme mb-2">{{ __('rbac.visibility_hint') }}</p>
            <form method="POST" action="{{ route('superadmin.group-visibility.update') }}">
                @csrf
                <div class="row g-1">
                    @foreach($visibilityGroups->whereIn('scope', ['course', 'both']) as $group)
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check form-check-sm">
                                <input class="form-check-input" type="checkbox" name="visible_groups[]"
                                       value="{{ $group->permission_group_id }}" id="g-{{ $group->permission_group_id }}"
                                       @checked($group->isVisibleToCourseAdmins())>
                                <label class="form-check-label small" for="g-{{ $group->permission_group_id }}">
                                    {{ $group->label() }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary btn-sm mt-2">{{ __('rbac.save') }}</button>
            </form>
        </div>
    </div>
</div>
