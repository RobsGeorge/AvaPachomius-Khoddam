@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">المحاضرات</h1>
        @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
            <a href="{{ route('sessions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> إنشاء محاضرات
            </a>
        @endif
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
                        <th>التاريخ</th>
                        <th>عنوان الجلسة</th>
                        <th>الدورة</th>
                        <th>عدد الحضور</th>
                        @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
                            <th>الإجراءات</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr>
                            <td>{{ $sessions->firstItem() + $loop->index }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ \Carbon\Carbon::parse($session->session_date)->format('Y-m-d') }}
                                </span>
                            </td>
                            <td>{{ $session->session_title }}</td>
                            <td>{{ $session->course->title ?? '—' }}</td>
                            <td>
                                <span class="badge bg-secondary">
                                    {{ $session->attendances->count() }}
                                </span>
                            </td>
                            @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
                                <td class="d-flex gap-2">
                                    <a href="{{ route('sessions.edit', $session->session_id) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('sessions.destroy', $session->session_id) }}"
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذه الجلسة؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                لا توجد جلسات بعد.
                                @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
                                    <a href="{{ route('sessions.create') }}">أنشئ الآن</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-center">
        {{ $sessions->links() }}
    </div>
</div>
@endsection
