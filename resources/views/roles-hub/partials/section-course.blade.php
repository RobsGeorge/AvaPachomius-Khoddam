@php
    $courseId = $course?->course_id;
    $open = $section === 'course';
@endphp
<div class="accordion-item app-card card shadow-sm mb-2 border-0">
    <h2 class="accordion-header">
        <button class="accordion-button {{ $open ? '' : 'collapsed' }} py-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#section-course" aria-expanded="{{ $open ? 'true' : 'false' }}">
            <i class="bi bi-shield-check me-2"></i>
            <span class="fw-semibold">{{ __('rbac.section_course') }}</span>
            @if($course)
                <span class="badge bg-secondary ms-2">{{ $course->title }}</span>
            @endif
        </button>
    </h2>
    <div id="section-course" class="accordion-collapse collapse {{ $open ? 'show' : '' }}" data-bs-parent="#rolesHubAccordion">
        <div class="accordion-body py-2 px-3">
            @if(! $course)
                <p class="text-muted-theme small mb-0">{{ __('rbac.select_course_hint') }}</p>
            @else
                <div class="row g-2">
                    @if($canManageCourse)
                        <div class="col-lg-4">
                            <details class="roles-hub-panel mb-2" open>
                                <summary class="roles-hub-summary">{{ __('rbac.create_role') }}</summary>
                                <form method="POST" action="{{ route('courses.roles.store', $course) }}" class="pt-2">
                                    @csrf
                                    <div class="mb-2">
                                        <input type="text" name="role_name" class="form-control form-control-sm" required maxlength="30"
                                               placeholder="{{ __('rbac.role_name') }}" value="{{ old('role_name') }}">
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="description" class="form-control form-control-sm" maxlength="255"
                                               placeholder="{{ __('rbac.description') }}" value="{{ old('description') }}">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('rbac.create_role') }}</button>
                                </form>
                            </details>

                            <details class="roles-hub-panel mb-2">
                                <summary class="roles-hub-summary">{{ __('rbac.copy_roles') }}</summary>
                                <form method="POST" action="{{ route('courses.roles.copy', $course) }}" class="pt-2">
                                    @csrf
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="use_templates" value="1" id="hub_use_templates">
                                        <label class="form-check-label small" for="hub_use_templates">{{ __('rbac.use_templates') }}</label>
                                    </div>
                                    <select name="source_course_id" class="form-select form-select-sm mb-2">
                                        <option value="">{{ __('rbac.copy_from_course') }}</option>
                                        @foreach($otherCourses as $c)
                                            <option value="{{ $c->course_id }}">{{ $c->title }} ({{ $c->year }})</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('rbac.copy_roles') }}</button>
                                </form>
                            </details>
                        </div>
                    @endif

                    <div class="{{ $canManageCourse ? 'col-lg-8' : 'col-12' }}">
                        @if($canManageCourse)
                            <details class="roles-hub-panel mb-2" open>
                                <summary class="roles-hub-summary">{{ __('rbac.roles_list') }} ({{ $roles->count() }})</summary>
                                <div class="table-responsive pt-1">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>{{ __('rbac.role_name') }}</th>
                                                <th>{{ __('rbac.users_count', ['count' => '']) }}</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($roles as $role)
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold">{{ $role->role_name }}</div>
                                                        @if($role->description ?? $role->role_decription)
                                                            <div class="small text-muted-theme">{{ $role->description ?? $role->role_decription }}</div>
                                                        @endif
                                                    </td>
                                                    <td>{{ $role->user_course_roles_count }}</td>
                                                    <td class="text-end text-nowrap">
                                                        <a href="{{ route('courses.roles.edit', [$course, $role]) }}" class="btn btn-sm btn-outline-primary">{{ __('rbac.permissions') }}</a>
                                                        @if($role->user_course_roles_count === 0)
                                                            <form method="POST" action="{{ route('courses.roles.destroy', [$course, $role]) }}" class="d-inline"
                                                                  onsubmit="return confirm(@json(__('rbac.confirm_delete')))">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('rbac.delete') }}</button>
                                                            </form>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="text-center text-muted-theme py-2">{{ __('rbac.no_roles') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @endif

                        @if($canAssignCourse || $canManageCourse)
                            <details class="roles-hub-panel mb-2" @if(! $canManageCourse) open @endif>
                                <summary class="roles-hub-summary">{{ __('rbac.assign_user') }}</summary>
                                <form method="POST" action="{{ route('courses.roles.assignments.store', $course) }}" class="row g-2 pt-2 align-items-end">
                                    @csrf
                                    <div class="col-md-5">
                                        <select name="user_id" class="form-select form-select-sm" required>
                                            <option value="">{{ __('rbac.user') }}</option>
                                            @foreach($assignUsers as $u)
                                                <option value="{{ $u->user_id }}">{{ $u->displayName() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="role_id" class="form-select form-select-sm" required>
                                            @foreach($roles as $r)
                                                <option value="{{ $r->role_id }}">{{ $r->role_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('rbac.assign_user') }}</button>
                                    </div>
                                </form>
                            </details>

                            <details class="roles-hub-panel">
                                <summary class="roles-hub-summary">{{ __('rbac.assignments') }} ({{ $assignments->count() }})</summary>
                                <div class="table-responsive pt-1">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr><th>{{ __('rbac.user') }}</th><th>{{ __('rbac.role') }}</th><th></th></tr>
                                        </thead>
                                        <tbody>
                                            @forelse($assignments as $a)
                                                <tr>
                                                    <td>{{ $a->user?->displayName() }}</td>
                                                    <td>{{ $a->role?->role_name }}</td>
                                                    <td class="text-end">
                                                        <form method="POST" action="{{ route('courses.roles.assignments.destroy', [$course, $a]) }}" class="d-inline">
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
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
