@extends('layouts.app')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');

    .content-container {
        font-family: 'Cairo', sans-serif;
        text-align: right;
        direction: rtl;
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
        color: #333;
    }

    .content-title {
        font-weight: 900;
        font-size: 2.5rem;
        margin-bottom: 2rem;
        color: #1a202c;
        text-align: center;
    }

    .content-table {
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .content-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .content-table th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem;
        font-weight: 700;
        text-align: center;
        border: none;
    }

    .content-table td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        text-align: center;
        vertical-align: middle;
    }

    .content-table tr:hover {
        background-color: #f7fafc;
    }

    .btn-link {
        display: inline-block;
        padding: 0.5rem 1rem;
        margin: 0.25rem;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-audio {
        background: #48bb78;
        color: white;
    }

    .btn-audio:hover {
        background: #38a169;
        color: white;
    }

    .btn-slides {
        background: #4299e1;
        color: white;
    }

    .btn-slides:hover {
        background: #3182ce;
        color: white;
    }

    .btn-feedback {
        background: #ed8936;
        color: white;
    }

    .btn-feedback:hover {
        background: #dd6b20;
        color: white;
    }

    .btn-feedback-submitted {
        background: #9f7aea;
        color: white;
        cursor: not-allowed;
    }

    .btn-feedback-submitted:hover {
        background: #9f7aea;
        color: white;
    }

    .no-content {
        text-align: center;
        padding: 3rem;
        color: #718096;
        font-size: 1.2rem;
    }

    .session-date {
        font-weight: 600;
        color: #2d3748;
    }

    .lecture-name {
        font-weight: 700;
        color: #4a5568;
    }

    .speaker-name {
        font-style: italic;
        color: #718096;
    }

    .add-content-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        display: inline-block;
        margin-bottom: 2rem;
        transition: all 0.3s ease;
    }

    .add-content-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        color: white;
    }
</style>

<div class="content-container">
    <h1 class="content-title">المحتوى التعليمي</h1>

    @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
        <a href="{{ route('contents.create') }}" class="add-content-btn">
            <i class="fas fa-plus"></i> إضافة محتوى جديد
        </a>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="content-table">
        @if($contents->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>عنوان الجلسة</th>
                        <th>تاريخ الجلسة</th>
                        <th>اسم المحاضرة</th>
                        <th>اسم المحاضر</th>
                        <th>الروابط</th>
                        <th>التغذية الراجعة</th>
                        @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                            <th>الإجراءات</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($contents as $content)
                        <tr>
                            <td class="session-title">{{ $content->session_title }}</td>
                            <td class="session-date">{{ $content->session_date ? $content->session_date->format('Y-m-d') : 'غير محدد' }}</td>
                            <td class="lecture-name">{{ $content->lecture_name }}</td>
                            <td class="speaker-name">{{ $content->speaker_name }}</td>
                            <td>
                                @if($content->audio_link)
                                    <a href="{{ $content->audio_link }}" target="_blank" class="btn-link btn-audio">
                                        <i class="fas fa-headphones"></i> الصوت
                                    </a>
                                @endif
                                @if($content->slides_link)
                                    <a href="{{ $content->slides_link }}" target="_blank" class="btn-link btn-slides">
                                        <i class="fas fa-file-powerpoint"></i> الشرائح
                                    </a>
                                @endif
                                @if(!$content->audio_link && !$content->slides_link)
                                    <span class="text-muted">لا توجد روابط</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $userFeedback = $content->userFeedback(Auth::id());
                                @endphp
                                @if($userFeedback)
                                    <a href="{{ route('contents.feedback', $content->content_id) }}" class="btn-link btn-feedback-submitted">
                                        <i class="fas fa-edit"></i> تعديل التغذية الراجعة
                                    </a>
                                @else
                                    <a href="{{ route('contents.feedback', $content->content_id) }}" class="btn-link btn-feedback">
                                        <i class="fas fa-comment"></i> إرسال تغذية راجعة
                                    </a>
                                @endif
                            </td>
                            @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                                <td>
                                    <a href="{{ route('contents.edit', $content->content_id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> تعديل
                                    </a>
                                    <form action="{{ route('contents.destroy', $content->content_id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المحتوى؟')">
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-content">
                <i class="fas fa-book-open fa-3x mb-3"></i>
                <p>لا يوجد محتوى تعليمي متاح حالياً</p>
                @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                    <a href="{{ route('contents.create') }}" class="btn btn-primary">إضافة أول محتوى</a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection

