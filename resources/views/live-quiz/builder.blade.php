@extends('layouts.app')

@section('title', __('pages.live_quiz_builder'))

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="page-title mb-1">{{ $liveQuiz->title }}</h1>
            <small class="text-muted-theme">{{ __('pages.live_quiz_code') }}: {{ $liveQuiz->join_code }}</small>
        </div>
        <a href="{{ route('live-quiz.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
    </div>

@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="app-card card">
                <div class="card-body p-4">
                    <h5 class="mb-3">{{ __('pages.live_quiz_add_question') }}</h5>
                    <form method="POST" action="{{ route('live-quiz.questions.store', $liveQuiz) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.question_type') }}</label>
                            <select name="question_type" class="form-select" id="question-type">
                                <option value="mcq">MCQ</option>
                                <option value="true_false">{{ __('pages.true_false') }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.prompt_text') }}</label>
                            <textarea name="prompt_text" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.prompt_image') }}</label>
                            <input type="file" name="prompt_image" class="form-control" accept="image/*">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">{{ __('pages.time_limit_seconds') }}</label>
                                <input type="number" name="time_limit_seconds" class="form-control" value="30" min="5" max="300">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ __('pages.points') }}</label>
                                <input type="number" step="0.25" name="points" class="form-control" value="1">
                            </div>
                        </div>
                        <div id="mcq-options">
                            @for($i = 0; $i < 4; $i++)
                                <div class="border rounded p-2 mb-2">
                                    <input type="text" name="options[{{ $i }}][label]" class="form-control mb-2" placeholder="{{ __('pages.option') }} {{ $i + 1 }}">
                                    <input type="file" name="option_images[{{ $i }}]" class="form-control mb-2" accept="image/*">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="options[{{ $i }}][is_correct]" value="1" id="opt-correct-{{ $i }}">
                                        <label class="form-check-label" for="opt-correct-{{ $i }}">{{ __('pages.correct_answer') }}</label>
                                    </div>
                                </div>
                            @endfor
                        </div>
                        <div id="tf-options" class="d-none">
                            <div class="form-check"><input class="form-check-input" type="radio" name="options[0][is_correct]" value="1" id="tf-true"><label class="form-check-label" for="tf-true">{{ __('pages.true') }} {{ __('pages.correct_answer') }}</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="options[1][is_correct]" value="1" id="tf-false"><label class="form-check-label" for="tf-false">{{ __('pages.false') }} {{ __('pages.correct_answer') }}</label></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-2">{{ __('pages.add') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="app-card card">
                <div class="card-body p-4">
                    <h5 class="mb-3">{{ __('pages.questions') }} ({{ $liveQuiz->questions->count() }})</h5>
                    @forelse($liveQuiz->questions as $question)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <strong>#{{ $question->order_index }} · {{ strtoupper($question->question_type) }}</strong>
                                <form method="POST" action="{{ route('live-quiz.questions.destroy', [$liveQuiz, $question]) }}" data-confirm="{{ __('pages.confirm_delete') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">{{ __('pages.delete') }}</button>
                                </form>
                            </div>
                            @if($question->prompt_text)<p class="mb-2 mt-2">{{ $question->prompt_text }}</p>@endif
                            @if($question->prompt_image_path)<img src="{{ asset('storage/'.$question->prompt_image_path) }}" class="img-fluid rounded mb-2" alt="">@endif
                            <ul class="mb-0">
                                @foreach($question->options as $opt)
                                    <li>@if($opt->is_correct)<i class="bi bi-check-circle-fill text-success"></i>@endif {{ $opt->label_text }} @if($opt->label_image_path)<img src="{{ asset('storage/'.$opt->label_image_path) }}" height="40">@endif</li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="text-muted-theme">{{ __('pages.live_quiz_no_questions') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('question-type').addEventListener('change', function () {
    const tf = this.value === 'true_false';
    document.getElementById('mcq-options').classList.toggle('d-none', tf);
    document.getElementById('tf-options').classList.toggle('d-none', !tf);
});
</script>
@endpush
