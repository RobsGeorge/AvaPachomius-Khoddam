@extends('layouts.app')

@section('title', __('pages.live_quiz_create'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <h1 class="page-title mb-4">{{ __('pages.live_quiz_create') }}</h1>

    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('live-quiz.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.title') }}</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.course_optional') }}</label>
                    <select name="course_id" class="form-select">
                        <option value="">{{ __('pages.none') }}</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->course_id }}" @selected(old('course_id') == $course->course_id)>{{ $course->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.live_quiz_mode') }}</label>
                    <select name="mode" class="form-select" id="quiz-mode">
                        <option value="individual" @selected(old('mode') === 'individual')>{{ __('pages.live_quiz_mode_individual') }}</option>
                        <option value="team" @selected(old('mode') === 'team')>{{ __('pages.live_quiz_mode_team') }}</option>
                    </select>
                </div>
                <div class="mb-3" id="team-count-wrap">
                    <label class="form-label">{{ __('pages.live_quiz_team_count') }}</label>
                    <input type="number" name="team_count" class="form-control" min="2" max="20" value="{{ old('team_count', 4) }}">
                </div>
                <button type="submit" class="btn btn-primary">{{ __('pages.create') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('quiz-mode').addEventListener('change', function () {
    document.getElementById('team-count-wrap').style.display = this.value === 'team' ? '' : 'none';
});
document.getElementById('quiz-mode').dispatchEvent(new Event('change'));
</script>
@endpush
