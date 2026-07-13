@php
    /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\Role>> $rolesByCourse */
    $selectId = $selectId ?? 'role_id';
    $selectName = $selectName ?? 'role_id';
    $courseSelectId = $courseSelectId ?? 'course_id';
    $selectedCourseId = $selectedCourseId ?? null;
    $selectedRoleId = $selectedRoleId ?? null;
    $required = $required ?? true;
@endphp
<select name="{{ $selectName }}" id="{{ $selectId }}" class="form-select" @if($required) required @endif>
    <option value="">{{ __('pages.select_role') }}</option>
    @foreach($rolesByCourse as $courseId => $courseRoles)
        @foreach($courseRoles as $role)
            <option value="{{ $role->role_id }}"
                    data-course-id="{{ $courseId }}"
                    @selected((string) old($selectName, $selectedRoleId) === (string) $role->role_id)>
                {{ $role->role_name }}
                @if($showCourseInLabel ?? false)
                    — {{ $role->course?->title }}
                @endif
            </option>
        @endforeach
    @endforeach
</select>

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-course-filtered-role-select]').forEach((wrapper) => {
            const courseSelect = document.getElementById(wrapper.dataset.courseSelect);
            const roleSelect = document.getElementById(wrapper.dataset.roleSelect);
            if (!courseSelect || !roleSelect) {
                return;
            }

            const filterRoles = () => {
                const courseId = courseSelect.value;
                Array.from(roleSelect.options).forEach((opt) => {
                    if (!opt.value) {
                        opt.hidden = false;
                        return;
                    }
                    const match = opt.dataset.courseId === courseId;
                    opt.hidden = !match;
                    if (!match && opt.selected) {
                        opt.selected = false;
                    }
                });
            };

            courseSelect.addEventListener('change', filterRoles);
            filterRoles();
        });
    });
    </script>
    @endpush
@endonce
