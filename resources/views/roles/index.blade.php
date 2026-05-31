@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">إدارة الأدوار</h1>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-5">
        <div class="card-header fw-semibold">قائمة الأدوار</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>اسم الدور</th>
                        <th>الوصف</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $role->role_name }}</td>
                            <td>{{ $role->role_decription }}</td>
                            <td>
                                <form method="POST" action="{{ route('roles.destroy', $role->role_id) }}"
                                      onsubmit="return confirm('هل أنت متأكد من حذف هذا الدور؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i> حذف
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">لا توجد أدوار بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">إضافة دور جديد</div>
        <div class="card-body">
            <form method="POST" action="{{ route('roles.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">اسم الدور <span class="text-danger">*</span></label>
                        <input type="text" name="role_name"
                               class="form-control @error('role_name') is-invalid @enderror"
                               value="{{ old('role_name') }}" maxlength="30" required>
                        @error('role_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">الوصف <span class="text-danger">*</span></label>
                        <input type="text" name="role_decription"
                               class="form-control @error('role_decription') is-invalid @enderror"
                               value="{{ old('role_decription') }}" maxlength="25" required>
                        @error('role_decription')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i> إضافة
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('user-course-roles.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-people"></i> تعيين الأدوار للمستخدمين
        </a>
    </div>
</div>
@endsection
