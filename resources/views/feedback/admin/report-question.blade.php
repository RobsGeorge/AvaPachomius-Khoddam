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
        <div class="table-responsive">
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
    </div>
    {{ $answers->links() }}
</div>
@endsection
