@extends('layouts.app')

@section('title', __('exams.builder_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <a href="{{ route('exams.dashboard') }}" class="text-muted small">{{ __('pages.exams_management') }}</a>
            <h1 class="page-title mb-1">{{ __('exams.builder_title') }}: {{ $exam->exam_name }}</h1>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <span class="badge bg-secondary">{{ __('exams.type_' . ($exam->exam_type ?? 'exam')) }}</span>
                <span class="badge {{ $exam->isOnline() ? 'bg-primary' : 'bg-dark' }}">{{ $exam->isOnline() ? __('exams.mode_online') : __('exams.mode_offline') }}</span>
                <span class="badge {{ $exam->is_published ? 'bg-success' : 'bg-warning text-dark' }}">
                    {{ $exam->is_published ? __('exams.published') : __('exams.draft') }}
                </span>
                <span class="badge bg-light text-dark border">{{ __('exams.total_points') }}: {{ $exam->total_points }}</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('exams.grades', $exam) }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-bar-chart"></i> {{ __('exams.grades_dashboard') }}
            </a>
            @if(! $exam->is_published)
                <form method="POST" action="{{ route('exams.publish', $exam) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-megaphone"></i> {{ __('exams.publish_exam') }}
                    </button>
                </form>
            @endif
        </div>
    </div>

@if($errors->any())
        <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="alert alert-info small">
        <strong>{{ __('exams.best_practices_title') }}:</strong>
        {{ __('exams.tip_timer') }} · {{ __('exams.tip_autosave') }}
    </div>

    @forelse($exam->questions as $i => $question)
        <div class="app-card card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between">
                <span>#{{ $i + 1 }} — {{ __('exams.type_' . str_replace('_', '_', $question->question_type === 'true_false' ? 'true_false' : $question->question_type)) }}</span>
                <span class="badge bg-primary">{{ $question->points }} {{ __('exams.points') }}</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('exams.questions.update', [$exam, $question]) }}"
                      class="js-edit-question-form" data-question-type="{{ $question->question_type }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">{{ __('exams.prompt') }}</label>
                        <textarea name="prompt" class="form-control" rows="3" required>{{ $question->prompt }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('exams.points') }}</label>
                        <input type="number" name="points" class="form-control" step="0.25" min="0.25"
                               value="{{ $question->points }}" required style="max-width:120px;">
                    </div>

                    @if($question->question_type === \App\Models\ExamQuestion::TYPE_ESSAY)
                        <div class="mb-3">
                            <label class="form-label">{{ __('exams.essay_ai_prompt') }}</label>
                            <textarea name="essay_ai_prompt" class="form-control" rows="2">{{ $question->essay_ai_prompt }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('exams.essay_keywords') }}</label>
                            <input type="text" name="essay_keywords" class="form-control" value="{{ $question->essay_keywords }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('exams.essay_rubric') }}</label>
                            <textarea name="essay_rubric" class="form-control" rows="2">{{ $question->essay_rubric }}</textarea>
                            <div class="form-text">{{ __('exams.essay_ai_hint') }}</div>
                        </div>
                    @elseif($question->question_type === \App\Models\ExamQuestion::TYPE_TRUE_FALSE)
                        @foreach($question->options as $j => $opt)
                            <div class="form-check mb-2">
                                <input type="radio" name="tf_correct" value="{{ $j }}"
                                       class="form-check-input" @checked($opt->is_correct)>
                                <input type="hidden" name="options[{{ $j }}][is_correct]"
                                       value="{{ $opt->is_correct ? 1 : 0 }}" class="tf-is-correct">
                                <input type="hidden" name="options[{{ $j }}][label]" value="{{ $opt->label }}">
                                <label class="form-check-label">{{ $opt->label }}</label>
                            </div>
                        @endforeach
                    @else
                        @foreach($question->options as $j => $opt)
                            <div class="input-group mb-2">
                                <div class="input-group-text">
                                    <input type="radio" name="mcq_correct" value="{{ $j }}"
                                           @checked($opt->is_correct)>
                                    <input type="hidden" name="options[{{ $j }}][is_correct]"
                                           value="{{ $opt->is_correct ? 1 : 0 }}" class="mcq-is-correct">
                                </div>
                                <input type="text" name="options[{{ $j }}][label]" class="form-control"
                                       value="{{ $opt->label }}" required>
                            </div>
                        @endforeach
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('pages.save') }}</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('exams.questions.destroy', [$exam, $question]) }}" class="mt-2"
                      onsubmit="return confirm(@json(__('pages.confirm_delete')))">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('pages.delete') }}</button>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-warning">{{ __('exams.no_questions_yet') }}</div>
    @endforelse

    <div class="app-card card shadow-sm">
        <div class="card-header fw-semibold">{{ __('exams.add_question') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('exams.questions.store', $exam) }}" id="addQuestionForm">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('exams.question_type') }}</label>
                        <select name="question_type" id="questionType" class="form-select" required>
                            <option value="mcq">{{ __('exams.type_mcq') }}</option>
                            <option value="true_false">{{ __('exams.type_true_false') }}</option>
                            <option value="essay">{{ __('exams.type_essay') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('exams.points') }}</label>
                        <input type="number" name="points" class="form-control" step="0.25" min="0.25" value="1" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('exams.prompt') }}</label>
                    <textarea name="prompt" class="form-control" rows="3" required></textarea>
                </div>

                <div id="mcqOptionsBlock">
                    <label class="form-label">{{ __('exams.options') }}</label>
                    @for($i = 0; $i < 4; $i++)
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input type="radio" name="mcq_correct" value="{{ $i }}" @checked($i === 0)>
                            </div>
                            <input type="text" name="options[{{ $i }}][label]" class="form-control" placeholder="{{ __('exams.add_option') }}">
                            <input type="hidden" name="options[{{ $i }}][is_correct]" value="{{ $i === 0 ? 1 : 0 }}" class="mcq-is-correct">
                        </div>
                    @endfor
                </div>

                <div id="tfOptionsBlock" class="d-none">
                    <label class="form-label">{{ __('exams.correct') }}</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tf_correct" value="0" checked>
                        <label class="form-check-label">{{ __('exams.true_label') }}</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tf_correct" value="1">
                        <label class="form-check-label">{{ __('exams.false_label') }}</label>
                    </div>
                </div>

                <div id="essayBlock" class="d-none">
                    <div class="mb-3">
                        <label class="form-label">{{ __('exams.essay_ai_prompt') }}</label>
                        <textarea name="essay_ai_prompt" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('exams.essay_keywords') }}</label>
                        <input type="text" name="essay_keywords" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('exams.essay_rubric') }}</label>
                        <textarea name="essay_rubric" class="form-control" rows="2"></textarea>
                        <div class="form-text">{{ __('exams.essay_ai_hint') }}</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> {{ __('exams.add_question') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const typeSelect = document.getElementById('questionType');
    const mcq = document.getElementById('mcqOptionsBlock');
    const tf = document.getElementById('tfOptionsBlock');
    const essay = document.getElementById('essayBlock');
    const form = document.getElementById('addQuestionForm');

    function syncType() {
        const t = typeSelect.value;
        mcq.classList.toggle('d-none', t !== 'mcq');
        tf.classList.toggle('d-none', t !== 'true_false');
        essay.classList.toggle('d-none', t !== 'essay');
    }
    typeSelect.addEventListener('change', syncType);
    syncType();

    function syncCorrectOptions(editForm, type) {
        if (type === 'true_false') {
            const correct = editForm.querySelector('[name=tf_correct]:checked')?.value || '0';
            editForm.querySelectorAll('.tf-is-correct').forEach(function (inp) {
                const match = inp.name.match(/options\[(\d+)\]/);
                inp.value = match && match[1] === correct ? '1' : '0';
            });
        }
        if (type === 'mcq') {
            const correct = editForm.querySelector('[name=mcq_correct]:checked')?.value || '0';
            editForm.querySelectorAll('.mcq-is-correct').forEach(function (inp) {
                const match = inp.name.match(/options\[(\d+)\]/);
                inp.value = match && match[1] === correct ? '1' : '0';
            });
        }
    }

    document.querySelectorAll('.js-edit-question-form').forEach(function (editForm) {
        editForm.addEventListener('submit', function () {
            syncCorrectOptions(editForm, editForm.dataset.questionType);
        });
    });

    form.addEventListener('submit', function () {
        if (typeSelect.value === 'true_false') {
            const correct = form.querySelector('[name=tf_correct]:checked')?.value || '0';
            ['0','1'].forEach(function (i) {
                let inp = form.querySelector('[name="options[' + i + '][is_correct]"]');
                if (!inp) {
                    inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'options[' + i + '][is_correct]';
                    form.appendChild(inp);
                }
                inp.value = i === correct ? '1' : '0';
            });
        }
        if (typeSelect.value === 'mcq') {
            syncCorrectOptions(form, 'mcq');
        }
    });
})();
</script>
@endpush
