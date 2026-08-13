@extends('layouts.app')

@section('title', __('pages.feedback_report'))

@section('content')
<div class="container py-4 animate-in" style="max-width:1100px;">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('pages.feedback_report') }}</h1>
            <p class="text-muted-theme mb-0">{{ $survey->title }} — {{ $survey->course?->title }}</p>
            <p class="small text-muted mb-0 mt-1">{{ __('pages.feedback_report_anonymous_notice') }}</p>
        </div>
        <a href="{{ route('feedback.surveys.edit', $survey) }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="app-card card"><div class="card-body text-center"><div class="display-6">{{ $submissions->total() }}</div><small>{{ __('pages.responses') }}</small></div></div></div>
        <div class="col-md-4"><div class="app-card card"><div class="card-body text-center"><div class="display-6">{{ $enrolledCount }}</div><small>{{ __('pages.enrolled_students') }}</small></div></div></div>
        <div class="col-md-4"><div class="app-card card"><div class="card-body text-center"><div class="display-6">{{ $enrolledCount > 0 ? round($submissions->total() / $enrolledCount * 100) : 0 }}%</div><small>{{ __('pages.completion_rate') }}</small></div></div></div>
    </div>

    <h5 class="mb-3">{{ __('pages.analysis_by_question') }}</h5>
    @foreach($aggregates as $item)
        @php $q = $item['question']; @endphp
        <div class="app-card card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <span class="badge bg-primary">{{ $q->question_type }}</span>
                        <strong class="ms-1">{{ $q->scopeLabel() }}</strong>
                        @if($item['numeric_avg'] !== null)
                            <span class="badge bg-success ms-2">{{ __('pages.average') }}: {{ $item['numeric_avg'] }}</span>
                        @endif
                        <span class="text-muted small ms-2">({{ $item['count'] }} {{ __('pages.answers') }})</span>
                    </div>
                    <a href="{{ route('feedback.surveys.report.question', [$survey, $q]) }}" class="btn btn-sm btn-outline-theme">{{ __('pages.view_details') }}</a>
                </div>
                @if(count($item['distribution']) > 0)
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        @foreach($item['distribution'] as $row)
                            <span class="badge bg-light text-dark border">{{ $row['value'] }}: {{ $row['count'] }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    <h5 class="mb-3 mt-4">{{ __('pages.responses_anonymous') }}</h5>
    <div class="app-card card">
        <div class="table-responsive d-none d-lg-block admin-table-desktop">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>{{ __('pages.response') }}</th><th>{{ __('pages.submitted_at') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse($submissions as $sub)
                        @php
                            $revealed = $activeReveals->has($sub->submission_id);
                            $label = $revealed
                                ? ($sub->user?->displayName() ?? __('pages.feedback_anonymous_response', ['id' => $sub->submission_id]))
                                : __('pages.feedback_anonymous_response', ['id' => $sub->submission_id]);
                            $pending = $pendingRequests->get($sub->submission_id);
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
                            <td>{{ $sub->submitted_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('feedback.surveys.report.submission', [$survey, $sub]) }}" class="btn btn-sm btn-outline-primary">{{ __('pages.view') }}</a>
                                @unless($revealed || $pending)
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#revealModal{{ $sub->submission_id }}">
                                        {{ __('pages.feedback_request_identity') }}
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">{{ __('pages.no_responses_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-lg-none admin-data-cards student-data-hub p-3">
            @forelse($submissions as $sub)
                @php
                    $revealed = $activeReveals->has($sub->submission_id);
                    $label = $revealed
                        ? ($sub->user?->displayName() ?? __('pages.feedback_anonymous_response', ['id' => $sub->submission_id]))
                        : __('pages.feedback_anonymous_response', ['id' => $sub->submission_id]);
                    $pending = $pendingRequests->get($sub->submission_id);
                @endphp
                <article class="data-card">
                    <div class="data-card-title">{{ $label }}</div>
                    <dl class="data-meta-list mb-3">
                        <div class="data-meta-row">
                            <dt>{{ __('pages.submitted_at') }}</dt>
                            <dd>{{ $sub->submitted_at?->format('Y-m-d H:i') }}</dd>
                        </div>
                    </dl>
                    <div class="data-card-actions d-grid gap-2">
                        <a href="{{ route('feedback.surveys.report.submission', [$survey, $sub]) }}" class="btn btn-sm btn-outline-primary w-100">{{ __('pages.view') }}</a>
                        @unless($revealed || $pending)
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100"
                                    data-bs-toggle="modal" data-bs-target="#revealModal{{ $sub->submission_id }}">
                                {{ __('pages.feedback_request_identity') }}
                            </button>
                        @endunless
                    </div>
                </article>
            @empty
                <p class="text-center text-muted py-4 mb-0">{{ __('pages.no_responses_yet') }}</p>
            @endforelse
        </div>
    </div>
    {{ $submissions->links() }}
</div>
@endsection

@push('modals')
@foreach($submissions as $sub)
    @unless($activeReveals->has($sub->submission_id) || $pendingRequests->has($sub->submission_id))
        <div class="modal fade" id="revealModal{{ $sub->submission_id }}" tabindex="-1" aria-labelledby="revealModalLabel{{ $sub->submission_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('feedback.surveys.report.reveal', [$survey, $sub]) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="revealModalLabel{{ $sub->submission_id }}">{{ __('pages.feedback_request_identity') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">{{ __('pages.feedback_reveal_reason_help') }}</p>
                        <label class="form-label" for="reason{{ $sub->submission_id }}">{{ __('pages.reason') }}</label>
                        <textarea class="form-control" id="reason{{ $sub->submission_id }}" name="reason" rows="3" required minlength="10" maxlength="2000"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('pages.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endunless
@endforeach
@endpush
