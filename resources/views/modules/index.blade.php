@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">الوحدات الدراسية</h1>
        <a href="{{ route('modules.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> إنشاء وحدة جديدة
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>اسم الوحدة</th>
                        <th>الوصف</th>
                        <th>المحاضرات</th>
                        <th>الدورات المرتبطة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($modules as $module)
                        <tr>
                            <td class="text-muted small">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $module->title }}</td>
                            <td class="text-muted small">{{ $module->description }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $module->lectures_count }}</span>
                            </td>
                            <td>
                                @forelse($module->courses as $course)
                                    <span class="badge bg-light text-dark border me-1">{{ $course->title }}</span>
                                @empty
                                    <span class="text-muted small">—</span>
                                @endforelse
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('modules.edit', $module->module_id) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> تعديل
                                    </a>
                                    <form method="POST" action="{{ route('modules.destroy', $module->module_id) }}"
                                          onsubmit="return confirm('حذف هذه الوحدة؟ سيتم حذف جميع محاضراتها.')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                لا توجد وحدات بعد.
                                <a href="{{ route('modules.create') }}">أنشئ الآن</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
