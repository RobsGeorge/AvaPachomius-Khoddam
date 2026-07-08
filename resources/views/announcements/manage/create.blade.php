@extends('layouts.app')

@section('title', __('announcements.create'))

@section('content')
<div class="container py-4 animate-in" style="max-width:920px;">
    <h1 class="page-title mb-4">{{ __('announcements.create') }}</h1>
    @include('announcements.manage.partials.form', [
        'action' => route('announcements.manage.store'),
        'method' => 'POST',
        'courses' => $courses,
        'students' => $students,
        'selectedCourse' => $selectedCourse,
    ])
</div>
@endsection
