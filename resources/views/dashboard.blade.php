@extends('layouts.app')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');

    .dashboard-container {
        font-family: 'Cairo', sans-serif;
        text-align: right;
        direction: rtl;
        max-width: 900px;
        margin: 0 auto;
        padding: 2rem;
        color: #333;
    }

    h1.dashboard-title {
        font-weight: 900;
        font-size: 2.5rem;
        margin-bottom: 2rem;
        color: #1a202c; /* dark slate */
    }

    h2.section-title {
        font-weight: 700;
        font-size: 1.75rem;
        margin-bottom: 0.75rem;
        color: #2c5282; /* blue */
        border-bottom: 3px solid #805ad5; /* violet underline */
        padding-bottom: 0.3rem;
    }

    p.section-desc {
        font-weight: 500;
        font-size: 1.1rem;
        color: #4a5568; /* gray-700 */
    }

    .hello-user {
        font-weight: 900;
        font-size: 3rem;
        text-align: center;
        margin: 4rem 0;
        background: linear-gradient(90deg, #3182ce, #805ad5);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        user-select: none;
    }
</style>

<div class="dashboard-container">
    <h1 class="dashboard-title">لوحة التحكم</h1>

    <div class="hello-user">
        مرحباً بك، {{ Auth::user()->first_name ?? 'المستخدم' }}
    </div>

    <section class="mb-10">
        <h2 class="section-title">الملف الشخصي</h2>
        <a href="{{ route('profile') }}" class="btn btn-primary mt-2">الملف الشخصي</a>
        <!-- Example: Show user info here -->
    </section>

    <section class="mb-10">
        <h2 class="section-title">الحضور</h2>
        @if(Auth::user()->roles->contains('role_name', 'Admin') || Auth::user()->roles->contains('role_name', 'Instructor'))
            <a href="{{ route('attendance.all') }}" class="btn btn-primary mt-2">عرض سجل الحضور الكامل</a>
        @else
            <a href="{{ route('attendance.user', ['userId' => Auth::user()->user_id]) }}" class="btn btn-primary mt-2">عرض سجل الحضور الخاص بي</a>
        @endif
    </section>

    <section class="mb-10">
        <h2 class="section-title">الإعلانات</h2>
        <p class="section-desc">الإعلانات الهامة</p>
        <!-- Add announcements list -->
    </section>

    <section class="mb-10">
        <h2 class="section-title">المنهج الدراسي</h2>
        <p class="section-desc">تفاصيل المنهج الدراسي والمحتوى التعليمي</p>
        <!-- Add curriculum details -->
    </section>

    <section class="mb-10">
        <h2 class="section-title">الامتحانات</h2>
        <a href="{{ route('exams.index') }}" class="btn btn-primary mt-2">عرض مواعيد ونتائج الامتحانات</a>
        @if(Auth::user()->roles->contains('role_name', 'Admin') || Auth::user()->roles->contains('role_name', 'Instructor'))
            <a href="{{ route('exams.dashboard') }}" class="btn btn-secondary mt-2">إدارة الامتحانات</a>
        @endif
    </section>
</div>
@endsection
