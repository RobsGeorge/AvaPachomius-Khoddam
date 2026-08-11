@extends('layouts.app')

@section('title', __('pages.student_response'))

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">
    <a href="{{ route('feedback.surveys.report', $survey) }}" class="btn btn-outline-secondary btn-sm mb-3">{{ __('pages.back') }}</a>
    <h1 class="page-title mb-1">{{ $identityLabel }}</h1>
    <p class="text-muted-theme mb-3">{{ $survey->title }} — {{ $submission->submitted_at?->format('Y-m-d H:i') }}</p>

    @if($canSeeIdentity)
        <div class="alert alert-warning small">{{ __('pages.feedback_identity_revealed_notice') }}</div>
    @elseif($pendingRequest)
        <div class="alert alert-secondary small">{{ __('pages.feedback_reveal_pending') }}</div>
    @else
        <div class="mb-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#revealSubmissionModal">
                {{ __('pages.feedback_request_identity') }}
            </button>
        </div>
    @endif

    <div class="app-card card">
        <div class="card-body p-4">
            @foreach($submission->answers->sortBy(fn($a) => $a->question?->order_index) as $answer)
                <div class="mb-4 pb-3 border-bottom">
                    <div class="fw-semibold">{{ $answer->question?->scopeLabel() }}</div>
                    <div class="mt-2 p-3 bg-light rounded">{{ $answer->displayValue() }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@unless($canSeeIdentity || $pendingRequest)
    <div class="modal fade" id="revealSubmissionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('feedback.surveys.report.reveal', [$survey, $submission]) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('pages.feedback_request_identity') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">{{ __('pages.feedback_reveal_reason_help') }}</p>
                    <label class="form-label" for="reasonSubmission">{{ __('pages.reason') }}</label>
                    <textarea class="form-control" id="reasonSubmission" name="reason" rows="3" required minlength="10" maxlength="2000"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('pages.submit') }}</button>
                </div>
            </form>
        </div>
    </div>
@endunless
@endsection
