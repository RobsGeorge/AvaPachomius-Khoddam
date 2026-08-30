@php
    $open = $section === 'service';
@endphp
<div class="accordion-item app-card card shadow-sm mb-2 border-0">
    <h2 class="accordion-header">
        <button class="accordion-button {{ $open ? '' : 'collapsed' }} py-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#section-service" aria-expanded="{{ $open ? 'true' : 'false' }}">
            <i class="bi bi-building me-2"></i>
            <span class="fw-semibold">{{ __('rbac.section_service') }}</span>
            @if($service ?? null)
                <span class="badge bg-info text-dark ms-2">{{ $service->localizedTitle() }}</span>
            @endif
        </button>
    </h2>
    <div id="section-service" class="accordion-collapse collapse {{ $open ? 'show' : '' }}" data-bs-parent="#rolesHubAccordion">
        <div class="accordion-body py-2 px-3">
            @if(($manageableServices ?? collect())->count() > 1)
                <form method="GET" action="{{ route('roles.hub') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <input type="hidden" name="section" value="service">
                    <label for="hub-service" class="small text-muted-theme mb-0">{{ __('service.label') }}</label>
                    <select name="service" id="hub-service" class="form-select form-select-sm" style="min-width: 12rem;" onchange="this.form.submit()">
                        @foreach($manageableServices as $s)
                            <option value="{{ $s->service_id }}" @selected(($service->service_id ?? null) == $s->service_id)>
                                {{ $s->localizedTitle() }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @elseif($service ?? null)
                <p class="small mb-3">
                    <span class="badge bg-primary-subtle text-primary-emphasis border">
                        <i class="bi bi-building me-1"></i>{{ $service->localizedTitle() }}
                    </span>
                </p>
            @endif

            @if(! ($service ?? null))
                <p class="text-muted-theme small mb-0">{{ __('service.select_hint') }}</p>
            @else
                <p class="small text-muted-theme mb-3">{{ __('service.no_academic_hint') }}</p>

                <div class="row g-2">
                    @if($canManageService)
                        <div class="col-lg-5">
                            <form method="POST" action="{{ route('services.roles.clone-templates', $service) }}" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-copy"></i> {{ __('service.clone_templates') }}
                                </button>
                            </form>
                            <details class="roles-hub-panel mb-2" open>
                                <summary class="roles-hub-summary">{{ __('service.create_role') }}</summary>
                                <form method="POST" action="{{ route('services.roles.store', $service) }}" class="pt-2">
                                    @csrf
                                    <div class="mb-2">
                                        <input type="text" name="role_name" class="form-control form-control-sm" required maxlength="80"
                                               placeholder="{{ __('rbac.role_name') }}" value="{{ old('role_name') }}">
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="description" class="form-control form-control-sm" maxlength="255"
                                               placeholder="{{ __('rbac.description') }}" value="{{ old('description') }}">
                                    </div>
                                    @foreach($servicePermissionGroups as $group)
                                        <div class="mb-2">
                                            <div class="small fw-semibold mb-1">{{ $group->label_en }}</div>
                                            @foreach($group->permissions as $perm)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permission_ids[]"
                                                           value="{{ $perm->permission_id }}" id="svc-perm-{{ $perm->permission_id }}">
                                                    <label class="form-check-label small" for="svc-perm-{{ $perm->permission_id }}">
                                                        {{ $perm->label() }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('service.create_role') }}</button>
                                </form>
                            </details>

                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('rbac.role') }}</th>
                                            <th>{{ __('rbac.assignments') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($serviceRoles as $role)
                                            <tr>
                                                <td>{{ $role->role_name }}</td>
                                                <td>{{ $role->user_service_roles_count }}</td>
                                                <td class="text-end">
                                                    <form method="POST" action="{{ route('services.roles.destroy', [$service, $role]) }}"
                                                          data-confirm="{{ __('pages.confirm_delete') }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-outline-danger btn-sm" type="submit">{{ __('rbac.delete') }}</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-muted small">{{ __('pages.no_records') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if($canAssignService || $canCrossAddService)
                        <div class="col-lg-7">
                            @if($canAssignService)
                                <details class="roles-hub-panel mb-2" open>
                                    <summary class="roles-hub-summary">{{ __('service.add_member') }}</summary>
                                    <form method="POST" action="{{ route('services.members.store', $service) }}" class="pt-2 row g-2">
                                        @csrf
                                        <div class="col-md-5">
                                            <select name="user_id" class="form-select form-select-sm" required>
                                                <option value="">{{ __('pages.select_option') }}</option>
                                                @foreach($serviceAssignUsers as $u)
                                                    <option value="{{ $u->user_id }}">{{ $u->first_name }} {{ $u->second_name }} ({{ $u->email }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <select name="role_id" class="form-select form-select-sm" required>
                                                <option value="">{{ __('pages.role') }}</option>
                                                @foreach($serviceRoles as $role)
                                                    <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('service.add_member') }}</button>
                                        </div>
                                    </form>
                                </details>
                            @endif

                            @if($canCrossAddService)
                                <details class="roles-hub-panel mb-2">
                                    <summary class="roles-hub-summary">{{ __('service.cross_add_member') }}</summary>
                                    <p class="small text-muted-theme mb-2">{{ __('service.cross_add_hint') }}</p>
                                    <form method="POST" action="{{ route('services.members.cross', $service) }}" class="pt-1 row g-2">
                                        @csrf
                                        <div class="col-md-5">
                                            <select name="user_id" class="form-select form-select-sm" required>
                                                <option value="">{{ __('pages.select_option') }}</option>
                                                @foreach($crossCandidateUsers as $u)
                                                    <option value="{{ $u->user_id }}">{{ $u->first_name }} {{ $u->second_name }} ({{ $u->email }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <select name="role_id" class="form-select form-select-sm">
                                                <option value="">{{ __('service.default_member_role') }}</option>
                                                @foreach($serviceRoles as $role)
                                                    <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">{{ __('service.cross_add_member') }}</button>
                                        </div>
                                    </form>
                                </details>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('pages.user') }}</th>
                                            <th>{{ __('rbac.role') }}</th>
                                            <th>{{ __('service.primary') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($serviceMembers as $member)
                                            <tr>
                                                <td>{{ $member->user?->first_name }} {{ $member->user?->second_name }}</td>
                                                <td>{{ $member->role?->role_name }}</td>
                                                <td>@if($member->is_primary)<span class="badge bg-success">{{ __('service.primary') }}</span>@endif</td>
                                                <td class="text-end">
                                                    @if($canAssignService)
                                                        <form method="POST" action="{{ route('services.members.destroy', [$service, $member->user]) }}"
                                                              data-confirm="{{ __('pages.confirm_delete') }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-outline-danger btn-sm" type="submit">{{ __('rbac.delete') }}</button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-muted small">{{ __('pages.no_records') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
