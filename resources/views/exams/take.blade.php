@extends('layouts.app')

@section('title', $exam->exam_name)

@push('styles')
<style>
.exam-floating-timer {
    position: fixed;
    top: 5rem;
    inset-inline-end: 1rem;
    z-index: 1050;
    min-width: 160px;
    padding: .85rem 1.1rem;
    border-radius: .75rem;
    background: var(--kh-primary, #4f46e5);
    color: #fff;
    box-shadow: 0 8px 24px rgba(0,0,0,.25);
    text-align: center;
    border: 3px solid transparent;
    transition: background .2s, border-color .2s, transform .2s;
}
.exam-floating-timer.exam-timer-warning {
    background: #ea580c;
    color: #fff;
    border-color: #000;
    box-shadow: 0 0 0 4px rgba(234,88,12,.35), 0 8px 28px rgba(0,0,0,.35);
    transform: scale(1.05);
}
.exam-floating-timer.exam-timer-warning #examTimerDisplay {
    font-size: 2rem;
    font-weight: 900;
    letter-spacing: .05em;
    text-shadow: 0 1px 2px #000;
}
.exam-floating-timer.exam-timer-critical {
    background: #b91c1c;
    color: #fff;
    border-color: #fff;
    animation: exam-timer-pulse 0.8s infinite;
}
@keyframes exam-timer-pulse {
    0%, 100% { box-shadow: 0 0 0 4px rgba(255,255,255,.5); }
    50% { box-shadow: 0 0 0 10px rgba(255,255,255,.15); }
}
.exam-save-status { font-size: .75rem; opacity: .9; }
.exam-question-card { scroll-margin-top: 6rem; }
.exam-nav-dot { width: 2rem; height: 2rem; }
</style>
@endpush

@section('content')
<div id="examTakeRoot"
     data-save-url="{{ route('exams.attempt.save', $schedule->schedule_id) }}"
     data-timer-url="{{ route('exams.attempt.timer', $schedule->schedule_id) }}"
     data-proctor-url="{{ route('exams.attempt.proctor', $schedule->schedule_id) }}"
     data-autosave-interval="{{ config('exams.autosave_interval_seconds', 30) }}"
     data-ends-at-ms="{{ $timer['ends_at'] ? \Illuminate\Support\Carbon::parse($timer['ends_at'])->getTimestamp() * 1000 : 0 }}"
     data-confirm-submit="{{ __('exams.submit_confirm') }}"
     data-msg-saving="{{ __('exams.save_status_saving') }}"
     data-msg-saved="{{ __('exams.save_status_saved') }}"
     data-msg-error="{{ __('exams.save_status_error') }}"
     data-proctor-warn="{{ __('exams.proctor_first_warning') }}"
     data-proctor-terminated="{{ __('exams.proctor_exam_terminated') }}">

    <div id="examFloatingTimer" class="exam-floating-timer">
        <div class="small opacity-75">{{ __('exams.timer_label') }}</div>
        <div id="examTimerDisplay" class="fs-4 fw-bold font-monospace">--:--</div>
        <div id="examSaveStatus" class="exam-save-status mt-1">{{ __('exams.save_status_saved') }}</div>
    </div>

    {{-- Proctor warning modal --}}
    <div class="modal fade" id="proctorWarningModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-warning">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> {{ __('exams.proctor_warning_title') }}</h5>
                </div>
                <div class="modal-body" id="proctorWarningBody">{{ __('exams.proctor_first_warning') }}</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" data-bs-dismiss="modal">{{ __('exams.proctor_acknowledge') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    <h1 class="h4 mb-1">{{ $exam->exam_name }}</h1>
                    @if($exam->exam_description)
                        <p class="text-muted small mb-0">{{ $exam->exam_description }}</p>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('exams.attempt.submit', $schedule->schedule_id) }}" id="examTakeForm">
                @csrf
                @foreach($questions as $index => $question)
                    @php
                        $qid = $question->question_id;
                        $savedAnswer = $saved[$qid] ?? $saved[(string)$qid] ?? null;
                    @endphp
                    <div class="app-card card shadow-sm mb-3 exam-question-card" id="question-{{ $qid }}"
                         data-question-id="{{ $qid }}"
                         data-question-type="{{ $question->question_type === \App\Models\ExamQuestion::TYPE_ESSAY ? 'essay' : 'choice' }}">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">#{{ $index + 1 }}</span>
                            <span class="badge bg-secondary">{{ $question->points }} {{ __('exams.points') }}</span>
                        </div>
                        <div class="card-body">
                            <p class="mb-3" style="white-space:pre-line;">{{ $question->prompt }}</p>

                            @if($question->question_type === \App\Models\ExamQuestion::TYPE_ESSAY)
                                <textarea name="answers[{{ $qid }}][text]" class="form-control" rows="6">{{ is_array($savedAnswer) ? ($savedAnswer['text'] ?? '') : '' }}</textarea>
                            @else
                                @foreach($question->options as $opt)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio"
                                               name="answers[{{ $qid }}][option_id]"
                                               id="opt-{{ $opt->option_id }}"
                                               value="{{ $opt->option_id }}"
                                               @checked(is_array($savedAnswer) && (string)($savedAnswer['option_id'] ?? '') === (string)$opt->option_id)>
                                        <label class="form-check-label" for="opt-{{ $opt->option_id }}">{{ $opt->label }}</label>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="d-flex justify-content-between align-items-center mb-5">
                    <span class="text-muted small">{{ __('exams.submit_confirm_body') }}</span>
                    <button type="submit" class="btn btn-success btn-lg" id="examSubmitBtn">
                        <i class="bi bi-check2-circle"></i> {{ __('exams.submit_exam') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-3">
            <div class="app-card card shadow-sm sticky-top" style="top:5rem;">
                <div class="card-header fw-semibold">{{ __('exams.question_nav') }}</div>
                <div class="card-body d-flex flex-wrap gap-2">
                    @foreach($questions as $index => $question)
                        <button type="button" class="btn btn-sm btn-outline-theme exam-nav-dot"
                                data-goto-question="{{ $question->question_id }}">
                            {{ $index + 1 }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/exam-take.js') }}?v=20260607"></script>
@endpush
