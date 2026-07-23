@php $open = $section === 'assignments'; @endphp
<div class="accordion-item app-card card shadow-sm mb-2 border-0">
    <h2 class="accordion-header">
        <button class="accordion-button {{ $open ? '' : 'collapsed' }} py-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#section-assignments">
            <i class="bi bi-people me-2"></i>
            <span class="fw-semibold">{{ __('rbac.section_assignments') }}</span>
            <span class="badge bg-secondary ms-2">{{ $allAssignments->count() }}</span>
        </button>
    </h2>
    <div id="section-assignments" class="accordion-collapse collapse {{ $open ? 'show' : '' }}" data-bs-parent="#rolesHubAccordion">
        <div class="accordion-body py-2 px-3">
            <details class="roles-hub-panel mb-2" open>
                <summary class="roles-hub-summary">{{ __('pages.assign_role_to_user') }}</summary>
                <form method="POST" action="{{ route('superadmin.store') }}" class="row g-2 pt-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">{{ __('pages.user') }}</option>
                            @foreach($users as $u)
                                <option value="{{ $u->user_id }}">{{ $u->displayName() }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="course_id" id="hub-assign-course" class="form-select form-select-sm" required>
                            <option value="">{{ __('pages.course') }}</option>
                            @foreach($manageableCourses as $c)
                                <option value="{{ $c->course_id }}" @selected($course && (int) $course->course_id === (int) $c->course_id)>{{ $c->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="role_id" id="hub-assign-role" class="form-select form-select-sm" required>
                            <option value="">{{ __('pages.role') }}</option>
                            @foreach($manageableCourses as $c)
                                @php $courseRoles = $rolesByCourse->get($c->course_id, collect()); @endphp
                                @if($courseRoles->isNotEmpty())
                                    <optgroup label="{{ $c->title }}" data-course-id="{{ $c->course_id }}">
                                        @foreach($courseRoles as $role)
                                            <option value="{{ $role->role_id }}" data-course-id="{{ $c->course_id }}">{{ $role->role_name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('pages.assign_role') }}</button>
                    </div>
                </form>
            </details>

            <details class="roles-hub-panel" open>
                <summary class="roles-hub-summary">{{ __('pages.all_role_assignments') }}</summary>
                <p class="small text-muted-theme mb-1 pt-1">{{ __('pages.account_status_admin_hint') }}</p>
                <div class="table-responsive pt-1">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('rbac.user') }}</th>
                                <th>{{ __('pages.course') }}</th>
                                <th>{{ __('rbac.role') }}</th>
                                <th>{{ __('pages.account_status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allAssignments as $a)
                                @php
                                    $accountStatus = $accountStatuses[$a->user_course_role_id]
                                        ?? \App\Services\PendingRegistrationService::unknownAccountStatus();
                                    $statusClass = match ($accountStatus['key']) {
                                        'active' => 'bg-success',
                                        'pending_otp' => 'bg-warning text-dark',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $a->user?->displayName() }}</td>
                                    <td>{{ $a->course?->title }}</td>
                                    <td>{{ $a->role?->role_name }}</td>
                                    <td>
                                        <span class="badge {{ $statusClass }}">{{ $accountStatus['label'] }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            @if($a->user && $accountStatus['key'] !== 'active')
                                                <form method="POST" action="{{ route('user-course-roles.send-registration-link', $a->user->user_id) }}" class="d-inline"
                                                      data-confirm="{{ __('pages.confirm_send_account_setup_email') }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ __('pages.send_account_setup_email') }}">
                                                        <i class="bi bi-envelope"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('superadmin.destroy', $a->user_course_role_id) }}" class="d-inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('rbac.delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted-theme py-2">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>

            @if($legacyRoles->isNotEmpty())
                <details class="roles-hub-panel mt-2">
                    <summary class="roles-hub-summary">{{ __('pages.available_roles') }} <span class="badge bg-warning text-dark">{{ __('pages.legacy_roles_badge') }}</span></summary>
                    <div class="table-responsive pt-1">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>{{ __('rbac.role_name') }}</th><th></th></tr></thead>
                            <tbody>
                                @foreach($legacyRoles as $role)
                                    <tr>
                                        <td>{{ $role->role_name }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('superadmin.roles.destroy', $role->role_id) }}" class="d-inline"
                                                  data-confirm="{{ __('rbac.confirm_delete') }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('rbac.delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('hub-assign-course')?.addEventListener('change', function () {
    const courseId = this.value;
    const roleSelect = document.getElementById('hub-assign-role');
    if (!roleSelect) return;
    Array.from(roleSelect.options).forEach(opt => {
        if (!opt.value) return;
        const match = opt.dataset.courseId === courseId;
        opt.hidden = !match;
        if (!match && opt.selected) opt.selected = false;
    });
});
</script>
@endpush
