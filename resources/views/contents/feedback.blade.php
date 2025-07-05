@extends('layouts.app')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');

    .feedback-container {
        font-family: 'Cairo', sans-serif;
        text-align: right;
        direction: rtl;
        max-width: 800px;
        margin: 0 auto;
        padding: 2rem;
        color: #333;
    }

    .feedback-title {
        font-weight: 900;
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: #1a202c;
        text-align: center;
    }

    .content-info {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        text-align: center;
    }

    .content-info h3 {
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .content-info p {
        margin-bottom: 0.5rem;
        opacity: 0.9;
    }

    .feedback-form {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 2rem;
    }

    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .section-title {
        font-weight: 700;
        font-size: 1.5rem;
        color: #2d3748;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-left: 0.5rem;
        color: #667eea;
    }

    .rating-group {
        margin-bottom: 1.5rem;
    }

    .rating-label {
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 0.5rem;
        display: block;
    }

    .star-rating {
        display: flex;
        flex-direction: row-reverse;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .star-rating input {
        display: none;
    }

    .star-rating label {
        font-size: 2rem;
        color: #cbd5e0;
        cursor: pointer;
        transition: color 0.3s ease;
    }

    .star-rating label:hover,
    .star-rating label:hover ~ label,
    .star-rating input:checked ~ label {
        color: #f6ad55;
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.75rem;
        font-size: 1rem;
        transition: border-color 0.3s ease;
        font-family: 'Cairo', sans-serif;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .btn-submit {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
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

    .alert-success {
        background: #c6f6d5;
        color: #22543d;
        border: 1px solid #9ae6b4;
    }

    .alert-danger {
        background: #fed7d7;
        color: #742a2a;
        border: 1px solid #feb2b2;
    }
</style>

<div class="feedback-container">
    <a href="{{ route('contents.index') }}" class="btn-back">
        <i class="fas fa-arrow-right"></i> العودة إلى المحتوى
    </a>

    <h1 class="feedback-title">التغذية الراجعة</h1>

    <div class="content-info">
        <h3>{{ $content->session_title }}</h3>
        <p><strong>المحاضرة:</strong> {{ $content->lecture_name }}</p>
        <p><strong>المحاضر:</strong> {{ $content->speaker_name }}</p>
        <p><strong>التاريخ:</strong> {{ $content->session_date ? $content->session_date->format('Y-m-d') : 'غير محدد' }}</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="feedback-form">
        <form action="{{ route('contents.store-feedback', $content->content_id) }}" method="POST">
            @csrf

            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-chalkboard-teacher"></i>
                    تقييم المحاضرة
                </h3>

                <div class="rating-group">
                    <label class="rating-label">تقييم المحاضرة (من 5 نجوم)</label>
                    <div class="star-rating">
                        @for($i = 5; $i >= 1; $i--)
                            <input type="radio" name="lecture_rating" value="{{ $i }}" id="lecture_{{ $i }}" 
                                   {{ $userFeedback && $userFeedback->lecture_rating == $i ? 'checked' : '' }}>
                            <label for="lecture_{{ $i }}">★</label>
                        @endfor
                    </div>
                </div>

                <div class="mb-3">
                    <label for="lecture_comments" class="form-label">تعليقات على المحاضرة</label>
                    <textarea class="form-control" id="lecture_comments" name="lecture_comments" rows="4" 
                              placeholder="اكتب تعليقاتك على المحاضرة...">{{ old('lecture_comments', $userFeedback ? $userFeedback->lecture_comments : '') }}</textarea>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-user-tie"></i>
                    تقييم المحاضر
                </h3>

                <div class="rating-group">
                    <label class="rating-label">تقييم المحاضر (من 5 نجوم)</label>
                    <div class="star-rating">
                        @for($i = 5; $i >= 1; $i--)
                            <input type="radio" name="speaker_rating" value="{{ $i }}" id="speaker_{{ $i }}"
                                   {{ $userFeedback && $userFeedback->speaker_rating == $i ? 'checked' : '' }}>
                            <label for="speaker_{{ $i }}">★</label>
                        @endfor
                    </div>
                </div>

                <div class="mb-3">
                    <label for="speaker_comments" class="form-label">تعليقات على المحاضر</label>
                    <textarea class="form-control" id="speaker_comments" name="speaker_comments" rows="4"
                              placeholder="اكتب تعليقاتك على المحاضر...">{{ old('speaker_comments', $userFeedback ? $userFeedback->speaker_comments : '') }}</textarea>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-comments"></i>
                    تغذية راجعة عامة
                </h3>

                <div class="mb-3">
                    <label for="general_feedback" class="form-label">تغذية راجعة عامة</label>
                    <textarea class="form-control" id="general_feedback" name="general_feedback" rows="4"
                              placeholder="اكتب أي تعليقات أو اقتراحات عامة...">{{ old('general_feedback', $userFeedback ? $userFeedback->general_feedback : '') }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i>
                {{ $userFeedback ? 'تحديث التغذية الراجعة' : 'إرسال التغذية الراجعة' }}
            </button>
        </form>
    </div>
</div>
@endsection 