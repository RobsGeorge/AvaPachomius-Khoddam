@extends('layouts.app')

@section('title', __('pages.feedback_report'))

@section('content')
<div class="container py-4 animate-in" style="max-width:1100px;">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('pages.feedback_report') }}</h1>
            <p class="text-muted-theme mb-0">{{ $survey->title }} — {{ $survey->course?->title }}</p>
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

    <h5 class="mb-3 mt-4">{{ __('pages.responses_by_student') }}</h5>
    <div class="app-card card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>{{ __('pages.student') }}</th><th>{{ __('pages.submitted_at') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse($submissions as $sub)
                        <tr>
                            <td>{{ $sub->user?->displayName() ?? '—' }}</td>
                            <td>{{ $sub->submitted_at?->format('Y-m-d H:i') }}</td>
                            <td><a href="{{ route('feedback.surveys.report.student', [$survey, $sub->user_id]) }}" class="btn btn-sm btn-outline-primary">{{ __('pages.view') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">{{ __('pages.no_responses_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $submissions->links() }}
</div>
@endsection
