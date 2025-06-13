@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">الواجبات</h2>
                    @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                    <a href="{{ route('assignments.create') }}" class="btn btn-primary">إضافة واجب جديد</a>
                    @endif
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>اسم الواجب</th>
                                    <th>الوصف</th>
                                    <th>الدرجة الكلية</th>
                                    <th>تاريخ التسليم</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment->assignment_name }}</td>
                                        <td>{{ Str::limit($assignment->assignment_description, 500) }}</td>
                                        <td>{{ $assignment->total_points }}</td>
                                        <td>{{ $assignment->due_date->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <a href="{{ route('assignments.show', $assignment) }}" class="btn btn-info btn-sm">عرض</a>
                                            @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                                            <a href="{{ route('assignments.edit', $assignment) }}" class="btn btn-warning btn-sm">تعديل</a>
                                            <form action="{{ route('assignments.destroy', $assignment) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من حذف هذا الواجب؟')">حذف</button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">لا توجد واجبات</td>
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