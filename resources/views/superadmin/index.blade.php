@extends('layouts.app')

@section('content')
<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-shield-lock-fill"></i> المشرف العام
        </span>
        <h1 class="mb-0">إدارة الأدوار والصلاحيات</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">

        {{-- Left column: assign role form + role definitions --}}
        <div class="col-lg-4">

            {{-- Assign Role --}}
            <div class="card shadow-sm mb-4 border-danger">
                <div class="card-header bg-danger text-white fw-semibold">
                    <i class="bi bi-person-plus-fill"></i> تعيين دور لمستخدم
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('superadmin.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">المستخدم</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">-- اختر --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->user_id }}"
                                        {{ old('user_id') == $user->user_id ? 'selected' : '' }}>
                                        {{ $user->first_name }} {{ $user->second_name }}
                                        ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">الدورة</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">-- اختر --</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->course_id }}"
                                        {{ old('course_id') == $course->course_id ? 'selected' : '' }}>
                                        {{ $course->title }} ({{ $course->year }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">الدور</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">-- اختر --</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->role_id }}"
                                        {{ old('role_id') == $role->role_id ? 'selected' : '' }}>
                                        {{ $role->role_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-check-circle"></i> تعيين
                        </button>
                    </form>
                </div>
            </div>

            {{-- Manage Role Definitions --}}
            <div class="card shadow-sm border-secondary">
                <div class="card-header fw-semibold">
                    <i class="bi bi-shield"></i> الأدوار المتاحة
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>الاسم</th><th>الوصف</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($roles as $role)
                                <tr>
                                    <td>{{ $role->role_name }}</td>
                                    <td class="text-muted small">{{ $role->role_decription }}</td>
                                    <td>
                                        <form method="POST"
                                              action="{{ route('superadmin.roles.destroy', $role->role_id) }}"
                                              onsubmit="return confirm('حذف الدور؟')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-outline-danger py-0 px-1">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-2">لا توجد أدوار</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <form method="POST" action="{{ route('superadmin.roles.store') }}" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="role_name" class="form-control form-control-sm"
                               placeholder="اسم الدور" maxlength="30" required>
                        <input type="text" name="role_decription" class="form-control form-control-sm"
                               placeholder="الوصف" maxlength="25" required>
                        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">
                            <i class="bi bi-plus"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right column: all current assignments --}}
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-people-fill"></i>
                    جميع تعيينات الأدوار
                    <span class="badge bg-secondary ms-1">{{ $assignments->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>المستخدم</th>
                                    <th>البريد</th>
                                    <th>الدورة</th>
                                    <th>الدور</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $a)
                                    <tr>
                                        <td>
                                            {{ $a->user->first_name ?? '—' }}
                                            {{ $a->user->second_name ?? '' }}
                                            @if($a->user->is_superadmin ?? false)
                                                <span class="badge bg-danger ms-1" title="مشرف عام">
                                                    <i class="bi bi-shield-lock-fill"></i>
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ $a->user->email ?? '—' }}</td>
                                        <td>{{ $a->course->title ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-primary">
                                                {{ $a->role->role_name ?? '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST"
                                                  action="{{ route('superadmin.destroy', $a->user_course_role_id) }}"
                                                  onsubmit="return confirm('إلغاء هذا التعيين؟')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            لا توجد تعيينات بعد.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
