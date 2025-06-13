@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">{{ $assignment->assignment_name }}</h2>
                    @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                    <div>
                        <a href="{{ route('assignments.edit', $assignment) }}" class="btn btn-warning">تعديل</a>
                        <form action="{{ route('assignments.destroy', $assignment) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الواجب؟')">حذف</button>
                        </form>
                    </div>
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

                    <div class="mb-4">
                        <h4>الوصف</h4>
                        <p>{{ $assignment->assignment_description }}</p>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4>الدرجة الكلية</h4>
                            <p>{{ $assignment->total_points }}</p>
                        </div>
                        <div class="col-md-6">
                            <h4>تاريخ التسليم</h4>
                            <p>{{ $assignment->due_date->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    @if($assignment->instructions)
                    <div class="mb-4">
                        <h4>التعليمات</h4>
                        <p>{{ $assignment->instructions }}</p>
                    </div>
                    @endif

                    @if($assignment->resources)
                    <div class="mb-4">
                        <h4>الموارد</h4>
                        <p>{{ $assignment->resources }}</p>
                    </div>
                    @endif

                    @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                    <div class="mb-4">
                        <h4>تقديم الواجب</h4>
                        <form action="{{ route('assignments.submit', $assignment) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="submission_content">المحتوى</label>
                                <textarea class="form-control @error('submission_content') is-invalid @enderror" 
                                          id="submission_content" name="submission_content" rows="5" required>{{ old('submission_content') }}</textarea>
                                @error('submission_content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label for="file">ملف مرفق (PDF فقط) <span class="text-danger">*</span></label>
                                <input type="file" class="form-control @error('file') is-invalid @enderror" 
                                       id="file" name="file" accept=".pdf" required>
                                <small class="form-text text-muted">يجب رفع ملف PDF. الحد الأقصى للحجم هو 10 ميجابايت</small>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary">تقديم الواجب</button>
                        </form>
                    </div>
                    @endif

                    @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                    <div class="mb-4">
                        <h4>التسليمات</h4>
                        @forelse($submissions as $submission)
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">{{ $submission->user->name }}</h5>
                                    <p class="card-text">
                                        <strong>تاريخ التسليم:</strong> {{ $submission->submitted_at->format('Y-m-d H:i') }}<br>
                                        <strong>المحتوى:</strong><br>
                                        {{ $submission->submission_content }}
                                    </p>
                                    @if($submission->file_path)
                                        <p>
                                            <a href="{{ Storage::url($submission->file_path) }}" target="_blank" class="btn btn-info btn-sm">
                                                عرض الملف المرفق
                                            </a>
                                        </p>
                                    @endif

                                    <form action="{{ route('assignments.grade', $submission) }}" method="POST" class="mt-3">
                                        @csrf
                                        <div class="form-group mb-3">
                                            <label for="points_earned">الدرجة</label>
                                            <input type="number" class="form-control @error('points_earned') is-invalid @enderror" 
                                                   id="points_earned" name="points_earned" 
                                                   value="{{ old('points_earned', $submission->points_earned) }}" 
                                                   min="0" max="{{ $assignment->total_points }}">
                                            @error('points_earned')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="feedback">التغذية الراجعة</label>
                                            <textarea class="form-control @error('feedback') is-invalid @enderror" 
                                                      id="feedback" name="feedback" rows="3">{{ old('feedback', $submission->feedback) }}</textarea>
                                            @error('feedback')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <button type="submit" class="btn btn-primary">تقييم</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p>لا توجد تسليمات حتى الآن</p>
                        @endforelse
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 