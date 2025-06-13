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
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">{{ $submission->user->first_name }} {{ $submission->user->second_name }}</h5>
                                        <span class="badge bg-info">{{ $submission->submitted_at->format('Y-m-d H:i') }}</span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6 class="fw-bold">المحتوى:</h6>
                                        <p class="card-text">{{ $submission->submission_content }}</p>
                                    </div>

                                    @if($submission->file_path)
                                        <div class="mb-3">
                                            <h6 class="fw-bold">الملف المرفق:</h6>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                                <a href="{{ Storage::url($submission->file_path) }}" 
                                                   target="_blank" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-download me-1"></i>
                                                    تحميل الملف
                                                </a>
                                                <a href="{{ Storage::url($submission->file_path) }}" 
                                                   target="_blank" 
                                                   class="btn btn-outline-info btn-sm ms-2">
                                                    <i class="fas fa-eye me-1"></i>
                                                    عرض الملف
                                                </a>
                                            </div>
                                        </div>
                                    @endif

                                    <form action="{{ route('assignments.grade', $submission) }}" method="POST" class="mt-3">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="points_earned" class="form-label">الدرجة</label>
                                                    <input type="number" 
                                                           class="form-control @error('points_earned') is-invalid @enderror" 
                                                           id="points_earned" 
                                                           name="points_earned" 
                                                           value="{{ old('points_earned', $submission->points_earned) }}" 
                                                           min="0" 
                                                           max="{{ $assignment->total_points }}">
                                                    @error('points_earned')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="feedback" class="form-label">التغذية الراجعة</label>
                                                    <textarea class="form-control @error('feedback') is-invalid @enderror" 
                                                              id="feedback" 
                                                              name="feedback" 
                                                              rows="3">{{ old('feedback', $submission->feedback) }}</textarea>
                                                    @error('feedback')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-check me-1"></i>
                                            تقييم
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                لا توجد تسليمات حتى الآن
                            </div>
                        @endforelse
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 