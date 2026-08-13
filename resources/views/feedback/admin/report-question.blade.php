@extends('layouts.app')

@section('title', __('pages.question_report'))

@section('content')
<div class="container py-4 animate-in" style="max-width:900px;">
    <a href="{{ route('feedback.surveys.report', $survey) }}" class="btn btn-outline-secondary btn-sm mb-3">{{ __('pages.back') }}</a>
    <h1 class="page-title mb-1">{{ $question->scopeLabel() }}</h1>
    <p class="text-muted-theme mb-2">{{ $survey->title }}</p>
    <p class="small text-muted mb-4">{{ __('pages.feedback_report_anonymous_notice') }}</p>

    @if($aggregate && $aggregate['numeric_avg'] !== null)
        <div class="alert alert-info">{{ __('pages.average') }}: <strong>{{ $aggregate['numeric_avg'] }}</strong></div>
    @endif

    <div class="app-card card">
        <div class="table-responsive d-none d-lg-block admin-table-desktop">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>{{ __('pages.response') }}</th><th>{{ __('pages.answer') }}</th><th>{{ __('pages.date') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach($answers as $answer)
                        @php
                            $sub = $answer->submission;
                            $revealed = $sub && $activeReveals->has($sub->submission_id);
                            $label = $revealed
                                ? ($sub->user?->displayName() ?? __('pages.feedback_anonymous_response', ['id' => $sub->submission_id]))
                                : __('pages.feedback_anonymous_response', ['id' => $sub?->submission_id ?? '—']);
                            $pending = $pendingByAnswer->get($answer->answer_id)
                                ?? ($sub ? $pendingBySubmission->get($sub->submission_id) : null);
                        @endphp
                        <tr>
                            <td>
                                {{ $label }}
                                @if($revealed)
                                    <span class="badge bg-warning text-dark">{{ __('pages.feedback_identity_revealed') }}</span>
                                @elseif($pending)
                                    <span class="badge bg-secondary">{{ __('pages.feedback_reveal_pending') }}</span>
                                @endif
                            </td>
                            <td>{{ $answer->displayValue() }}</td>
                            <td>{{ $sub?->submitted_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                @if($sub && ! $revealed && ! $pending)
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#revealAnswer{{ $answer->answer_id }}">
                                        {{ __('pages.feedback_request_identity') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-lg-none admin-data-cards student-data-hub p-3">
            @foreach($answers as $answer)
                @php
                    $sub = $answer->submission;
                    $revealed = $sub && $activeReveals->has($sub->submission_id);
                    $label = $revealed
                        ? ($sub->user?->displayName() ?? __('pages.feedback_anonymous_response', ['id' => $sub->submission_id]))
                        : __('pages.feedback_anonymous_response', ['id' => $sub?->submission_id ?? '—']);
                    $pending = $pendingByAnswer->get($answer->answer_id)
                        ?? ($sub ? $pendingBySubmission->get($sub->submission_id) : null);
                @endphp
                <article class="data-card">
                    <div class="data-card-title">{{ $label }}</div>
                    <dl class="data-meta-list mb-3">
                        <div class="data-meta-row">
                            <dt>{{ __('pages.answer') }}</dt>
                            <dd>{{ $answer->displayValue() }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('pages.date') }}</dt>
                            <dd>{{ $sub?->submitted_at?->format('Y-m-d H:i') }}</dd>
                        </div>
                    </dl>
                    @if($sub && ! $revealed && ! $pending)
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100"
                                data-bs-toggle="modal" data-bs-target="#revealAnswer{{ $answer->answer_id }}">
                            {{ __('pages.feedback_request_identity') }}
                        </button>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
    {{ $answers->links() }}
</div>
@endsection

@push('modals')
@foreach($answers as $answer)
    @php
        $sub = $answer->submission;
        $revealed = $sub && $activeReveals->has($sub->submission_id);
        $pending = $pendingByAnswer->get($answer->answer_id)
            ?? ($sub ? $pendingBySubmission->get($sub->submission_id) : null);
    @endphp
    @if($sub && ! $revealed && ! $pending)
        <div class="modal fade" id="revealAnswer{{ $answer->answer_id }}" tabindex="-1" aria-labelledby="revealAnswerLabel{{ $answer->answer_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('feedback.surveys.report.reveal', [$survey, $sub]) }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="answer_id" value="{{ $answer->answer_id }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="revealAnswerLabel{{ $answer->answer_id }}">{{ __('pages.feedback_request_identity') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">{{ __('pages.feedback_reveal_reason_help') }}</p>
                        <label class="form-label" for="reasonAns{{ $answer->answer_id }}">{{ __('pages.reason') }}</label>
                        <textarea class="form-control" id="reasonAns{{ $answer->answer_id }}" name="reason" rows="3" required minlength="10" maxlength="2000"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('pages.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
@endpush
