@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">تعيين الأدوار</h1>
        <a href="{{ route('user-course-roles.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> تعيين دور جديد
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>المستخدم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الدورة</th>
                        <th>الدور</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                {{ $assignment->user->first_name ?? '—' }}
                                {{ $assignment->user->second_name ?? '' }}
                            </td>
                            <td>{{ $assignment->user->email ?? '—' }}</td>
                            <td>{{ $assignment->course->title ?? '—' }}</td>
                            <td>
                                <span class="badge bg-primary">
                                    {{ $assignment->role->role_name ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <form method="POST"
                                      action="{{ route('user-course-roles.destroy', $assignment->user_course_role_id) }}"
                                      onsubmit="return confirm('هل أنت متأكد من إلغاء هذا التعيين؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-x-circle"></i> إلغاء
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">لا توجد تعيينات بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-shield"></i> إدارة الأدوار
        </a>
    </div>
</div>
@endsection
