@extends('layouts.app')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');

    .content-form-container {
        font-family: 'Cairo', sans-serif;
        text-align: right;
        direction: rtl;
        max-width: 800px;
        margin: 0 auto;
        padding: 2rem;
        color: #333;
    }

    .form-title {
        font-weight: 900;
        font-size: 2.5rem;
        margin-bottom: 2rem;
        color: #1a202c;
        text-align: center;
    }

    .content-form {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 0.5rem;
        display: block;
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.75rem;
        font-size: 1rem;
        transition: border-color 0.3s ease;
        font-family: 'Cairo', sans-serif;
        width: 100%;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .form-control.is-invalid {
        border-color: #e53e3e;
    }

    .invalid-feedback {
        color: #e53e3e;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    .btn-submit {
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        color: white;
        border: none;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        margin-top: 1rem;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-back {
        background: #718096;
        color: white;
        text-decoration: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .btn-back:hover {
        background: #4a5568;
        color: white;
    }

    .alert {
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .alert-danger {
        background: #fed7d7;
        color: #742a2a;
        border: 1px solid #feb2b2;
    }

    .required-field::after {
        content: " *";
        color: #e53e3e;
    }
</style>

<div class="content-form-container">
    <a href="{{ route('contents.index') }}" class="btn-back">
        <i class="fas fa-arrow-right"></i> العودة إلى المحتوى
    </a>

    <h1 class="form-title">تعديل المحتوى</h1>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="content-form">
        <form action="{{ route('contents.update', $content->content_id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="title" class="form-label required-field">عنوان المحتوى</label>
                <input type="text" class="form-control @error('title') is-invalid @enderror" 
                       id="title" name="title" value="{{ old('title', $content->title) }}" required>
                @error('title')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="session_title" class="form-label required-field">عنوان الجلسة</label>
                <input type="text" class="form-control @error('session_title') is-invalid @enderror" 
                       id="session_title" name="session_title" value="{{ old('session_title', $content->session_title) }}" required>
                @error('session_title')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="session_date" class="form-label required-field">تاريخ الجلسة</label>
                <input type="date" class="form-control @error('session_date') is-invalid @enderror" 
                       id="session_date" name="session_date" 
                       value="{{ old('session_date', $content->session_date ? $content->session_date->format('Y-m-d') : '') }}" required>
                @error('session_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="lecture_name" class="form-label required-field">اسم المحاضرة</label>
                <input type="text" class="form-control @error('lecture_name') is-invalid @enderror" 
                       id="lecture_name" name="lecture_name" value="{{ old('lecture_name', $content->lecture_name) }}" required>
                @error('lecture_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="speaker_name" class="form-label required-field">اسم المحاضر</label>
                <input type="text" class="form-control @error('speaker_name') is-invalid @enderror" 
                       id="speaker_name" name="speaker_name" value="{{ old('speaker_name', $content->speaker_name) }}" required>
                @error('speaker_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="audio_link" class="form-label">رابط الصوت</label>
                <input type="url" class="form-control @error('audio_link') is-invalid @enderror" 
                       id="audio_link" name="audio_link" value="{{ old('audio_link', $content->audio_link) }}" 
                       placeholder="https://example.com/audio.mp3">
                @error('audio_link')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="slides_link" class="form-label">رابط الشرائح</label>
                <input type="url" class="form-control @error('slides_link') is-invalid @enderror" 
                       id="slides_link" name="slides_link" value="{{ old('slides_link', $content->slides_link) }}" 
                       placeholder="https://example.com/slides.pdf">
                @error('slides_link')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="description" class="form-label">وصف المحتوى</label>
                <textarea class="form-control @error('description') is-invalid @enderror" 
                          id="description" name="description" rows="4" 
                          placeholder="اكتب وصفاً مختصراً للمحتوى...">{{ old('description', $content->description) }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> تحديث المحتوى
            </button>
        </form>
    </div>
</div>
@endsection

