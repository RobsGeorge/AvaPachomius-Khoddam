@php $open = $section === 'system'; @endphp
<div class="accordion-item app-card card shadow-sm mb-2 border-0">
    <h2 class="accordion-header">
        <button class="accordion-button {{ $open ? '' : 'collapsed' }} py-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#section-system">
            <i class="bi bi-person-gear me-2"></i>
            <span class="fw-semibold">{{ __('rbac.system_roles') }}</span>
        </button>
    </h2>
    <div id="section-system" class="accordion-collapse collapse {{ $open ? 'show' : '' }}" data-bs-parent="#rolesHubAccordion">
        <div class="accordion-body py-2 px-3">
            <details class="roles-hub-panel mb-2" open>
                <summary class="roles-hub-summary">{{ __('rbac.create_role') }}</summary>
                <form method="POST" action="{{ route('superadmin.system-roles.store') }}" class="pt-2">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <input type="text" name="role_name" class="form-control form-control-sm" placeholder="{{ __('rbac.role_name') }}" required>
                        </div>
                        <div class="col-md-8">
                            <input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('rbac.description') }}">
                        </div>
                    </div>
                    @foreach($systemGroups as $group)
                        <details class="roles-hub-subpanel mb-1">
                            <summary class="roles-hub-subsummary">{{ $group->label() }}</summary>
                            <div class="row g-1 pt-1">
                                @foreach($group->permissions as $perm)
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check form-check-sm">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->permission_id }}"
                                                   id="sys-new-{{ $perm->permission_id }}">
                                            <label class="form-check-label small" for="sys-new-{{ $perm->permission_id }}">{{ $perm->label() }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                    <button type="submit" class="btn btn-primary btn-sm mt-2">{{ __('rbac.create_role') }}</button>
                </form>
            </details>

            <details class="roles-hub-panel mb-2">
                <summary class="roles-hub-summary">{{ __('rbac.assign_user') }}</summary>
                <form method="POST" action="{{ route('superadmin.system-roles.assign') }}" class="row g-2 pt-2 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">{{ __('rbac.user') }}</option>
                            @foreach($users as $u)
                                <option value="{{ $u->user_id }}">{{ $u->displayName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <select name="role_id" class="form-select form-select-sm" required>
                            @foreach($systemRoles as $r)
                                <option value="{{ $r->role_id }}">{{ $r->role_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('rbac.assign_user') }}</button>
                    </div>
                </form>
            </details>

            <details class="roles-hub-panel" open>
                <summary class="roles-hub-summary">{{ __('rbac.assignments') }} ({{ $systemAssignments->count() }})</summary>
                <div class="table-responsive pt-1">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>{{ __('rbac.user') }}</th><th>{{ __('rbac.role') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse($systemAssignments as $a)
                                <tr>
                                    <td>{{ $a->user?->displayName() }}</td>
                                    <td>{{ $a->role?->role_name }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('superadmin.system-roles.assignments.destroy', $a) }}" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('rbac.delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted-theme py-2">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    </div>
</div>
