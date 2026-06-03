@extends('layouts.app')

@section('title', __('pages.learning_content'))

@section('content')
<div class="container py-4 animate-in">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('pages.learning_content') }}</h1>
        @if(auth()->user()->hasAnyRole(['admin', 'instructor']))
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('modules.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-collection"></i> {{ __('pages.modules_title') }}
                </a>
                <a href="{{ route('contents.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> {{ __('pages.add_new_content') }}
                </a>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($courses->isEmpty())
        <div class="alert alert-info">{{ __('pages.no_courses_for_content') }}</div>
    @else
        <p class="text-muted-theme mb-4">{{ __('pages.curriculum_by_module_hint') }}</p>
        <div class="row g-3">
            @foreach($courses as $course)
                <div class="col-md-6 col-lg-4">
                    <div class="app-card card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">{{ $course->title }}</h5>
                            @if($course->year)
                                <p class="text-muted small mb-3">{{ $course->year }}</p>
                            @endif
                            <a href="{{ route('course-content.show', $course->course_id) }}"
                               class="btn btn-primary mt-auto">
                                <i class="bi bi-journal-bookmark"></i> {{ __('pages.view_curriculum') }}
                            </a>
                            @if(auth()->user()->hasAnyRole(['admin', 'instructor']))
                                <a href="{{ route('course-content.admin', $course->course_id) }}"
                                   class="btn btn-outline-theme btn-sm mt-2">
                                    <i class="bi bi-pencil-square"></i> {{ __('pages.manage_content') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
