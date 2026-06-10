@extends('layouts.app')

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">

    <div class="mb-3">
        <a href="{{ route('curriculum.show', $course->course_id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> {{ __('pages.back_to_curriculum') }}
        </a>
    </div>

    <div class="text-center mb-4">
        <h1 class="page-title mb-1">{{ __('pages.module_feedback_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ $course->title }} — {{ $module->title }}</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST"
                  action="{{ route('module-feedback.store', [$course->course_id, $module->module_id]) }}">
                @csrf

                <div class="border-bottom pb-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-play-circle text-primary"></i> {{ __('pages.rate_lecture') }}</h5>
                    <x-star-rating name="lecture_rating" :label="__('pages.rating')" :value="$userFeedback?->lecture_rating" />
                    <textarea name="lecture_comments" class="form-control" rows="2"
                              placeholder="{{ __('pages.comments_optional') }}">{{ old('lecture_comments', $userFeedback?->lecture_comments) }}</textarea>
                </div>

                <div class="border-bottom pb-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-person-video3 text-primary"></i> {{ __('pages.rate_speaker') }}</h5>
                    <x-star-rating name="speaker_rating" :label="__('pages.rating')" :value="$userFeedback?->speaker_rating" />
                    <textarea name="speaker_comments" class="form-control" rows="2"
                              placeholder="{{ __('pages.comments_optional') }}">{{ old('speaker_comments', $userFeedback?->speaker_comments) }}</textarea>
                </div>

                <div class="border-bottom pb-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-tools text-primary"></i> {{ __('pages.rate_workshop') }}</h5>
                    <x-star-rating name="workshop_rating" :label="__('pages.rating')" :value="$userFeedback?->workshop_rating" />
                    <textarea name="workshop_comments" class="form-control" rows="2"
                              placeholder="{{ __('pages.comments_optional') }}">{{ old('workshop_comments', $userFeedback?->workshop_comments) }}</textarea>
                </div>

                <div class="border-bottom pb-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-clock text-primary"></i> {{ __('pages.rate_timing') }}</h5>
                    <x-star-rating name="timing_rating" :label="__('pages.rating')" :value="$userFeedback?->timing_rating" />
                    <textarea name="timing_comments" class="form-control" rows="2"
                              placeholder="{{ __('pages.comments_optional') }}">{{ old('timing_comments', $userFeedback?->timing_comments) }}</textarea>
                </div>

                <div class="border-bottom pb-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-journal-text text-primary"></i> {{ __('pages.rate_content') }}</h5>
                    <x-star-rating name="content_rating" :label="__('pages.rating')" :value="$userFeedback?->content_rating" />
                    <textarea name="content_comments" class="form-control" rows="2"
                              placeholder="{{ __('pages.comments_optional') }}">{{ old('content_comments', $userFeedback?->content_comments) }}</textarea>
                </div>

                <div class="mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-chat-left-text text-primary"></i> {{ __('pages.additional_notes') }}</h5>
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="{{ __('pages.notes_optional') }}">{{ old('notes', $userFeedback?->notes) }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-send"></i>
                    {{ $userFeedback ? __('pages.update_feedback') : __('pages.submit_feedback') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
