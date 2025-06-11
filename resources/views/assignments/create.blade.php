@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">إضافة واجب جديد</h2>
                </div>

                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form action="{{ route('assignments.store') }}" method="POST">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="assignment_name">اسم الواجب</label>
                            <input type="text" class="form-control @error('assignment_name') is-invalid @enderror" 
                                   id="assignment_name" name="assignment_name" value="{{ old('assignment_name') }}" required>
                            @error('assignment_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="assignment_description">وصف الواجب</label>
                            <textarea class="form-control @error('assignment_description') is-invalid @enderror" 
                                      id="assignment_description" name="assignment_description" rows="3" required>{{ old('assignment_description') }}</textarea>
                            @error('assignment_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="total_points">الدرجة الكلية</label>
                            <input type="number" class="form-control @error('total_points') is-invalid @enderror" 
                                   id="total_points" name="total_points" value="{{ old('total_points') }}" min="1" required>
                            @error('total_points')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="due_date">تاريخ التسليم</label>
                            <input type="datetime-local" class="form-control @error('due_date') is-invalid @enderror" 
                                   id="due_date" name="due_date" value="{{ old('due_date') }}" required>
                            @error('due_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="instructions">التعليمات</label>
                            <textarea class="form-control @error('instructions') is-invalid @enderror" 
                                      id="instructions" name="instructions" rows="3">{{ old('instructions') }}</textarea>
                            @error('instructions')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="resources">الموارد</label>
                            <textarea class="form-control @error('resources') is-invalid @enderror" 
                                      id="resources" name="resources" rows="3">{{ old('resources') }}</textarea>
                            @error('resources')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">إضافة الواجب</button>
                            <a href="{{ route('assignments.index') }}" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 