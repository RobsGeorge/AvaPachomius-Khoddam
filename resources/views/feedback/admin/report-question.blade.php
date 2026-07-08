@extends('layouts.app')

@section('title', __('pages.question_report'))

@section('content')
<div class="container py-4 animate-in" style="max-width:900px;">
    <a href="{{ route('feedback.surveys.report', $survey) }}" class="btn btn-outline-secondary btn-sm mb-3">{{ __('pages.back') }}</a>
    <h1 class="page-title mb-1">{{ $question->scopeLabel() }}</h1>
    <p class="text-muted-theme mb-4">{{ $survey->title }}</p>

    @if($aggregate && $aggregate['numeric_avg'] !== null)
        <div class="alert alert-info">{{ __('pages.average') }}: <strong>{{ $aggregate['numeric_avg'] }}</strong></div>
    @endif

    <div class="app-card card">
        <div class="table-responsive d-none d-lg-block admin-table-desktop">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>{{ __('pages.student') }}</th><th>{{ __('pages.answer') }}</th><th>{{ __('pages.date') }}</th></tr></thead>
                <tbody>
                    @foreach($answers as $answer)
                        <tr>
                            <td>{{ $answer->submission?->user?->displayName() }}</td>
                            <td>{{ $answer->displayValue() }}</td>
                            <td>{{ $answer->submission?->submitted_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-lg-none admin-data-cards student-data-hub p-3">
            @foreach($answers as $answer)
                <article class="data-card">
                    <div class="data-card-title">{{ $answer->submission?->user?->displayName() }}</div>
                    <dl class="data-meta-list mb-0">
                        <div class="data-meta-row">
                            <dt>{{ __('pages.answer') }}</dt>
                            <dd>{{ $answer->displayValue() }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('pages.date') }}</dt>
                            <dd>{{ $answer->submission?->submitted_at?->format('Y-m-d H:i') }}</dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>
    </div>
    {{ $answers->links() }}
</div>
@endsection
