@php $open = $section === 'templates'; @endphp
<div class="accordion-item app-card card shadow-sm mb-2 border-0">
    <h2 class="accordion-header">
        <button class="accordion-button {{ $open ? '' : 'collapsed' }} py-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#section-templates">
            <i class="bi bi-diagram-3 me-2"></i>
            <span class="fw-semibold">{{ __('rbac.manage_templates') }}</span>
            <span class="badge bg-secondary ms-2">{{ $templates->count() }}</span>
        </button>
    </h2>
    <div id="section-templates" class="accordion-collapse collapse {{ $open ? 'show' : '' }}" data-bs-parent="#rolesHubAccordion">
        <div class="accordion-body py-2 px-3">
            <p class="small text-muted-theme mb-2">{{ __('rbac.templates_hint') }}</p>
            @foreach($templates as $template)
                <details class="roles-hub-panel mb-2" @if($loop->first && $open) open @endif>
                    <summary class="roles-hub-summary">{{ $template->role_name }}</summary>
                    <form method="POST" action="{{ route('superadmin.templates.update', $template) }}" class="pt-2">
                        @csrf @method('PUT')
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <input type="text" name="role_name" class="form-control form-control-sm" value="{{ $template->role_name }}" required>
                            </div>
                            <div class="col-md-8">
                                <input type="text" name="description" class="form-control form-control-sm" value="{{ $template->description }}" placeholder="{{ __('rbac.description') }}">
                            </div>
                        </div>
                        @foreach($templateGroups as $group)
                            <details class="roles-hub-subpanel mb-1">
                                <summary class="roles-hub-subsummary">{{ $group->label() }} ({{ $group->permissions->count() }})</summary>
                                <div class="row g-1 pt-1">
                                    @foreach($group->permissions as $perm)
                                        <div class="col-md-6 col-lg-4">
                                            <div class="form-check form-check-sm">
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->permission_id }}"
                                                       id="t-{{ $template->role_id }}-{{ $perm->permission_id }}"
                                                       @checked($template->permissions->contains('permission_id', $perm->permission_id))>
                                                <label class="form-check-label small" for="t-{{ $template->role_id }}-{{ $perm->permission_id }}">{{ $perm->label() }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                        <button type="submit" class="btn btn-primary btn-sm mt-2">{{ __('rbac.save') }}</button>
                    </form>
                </details>
            @endforeach
        </div>
    </div>
</div>
