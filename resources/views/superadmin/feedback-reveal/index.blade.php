@extends('layouts.app')

@section('title', __('pages.feedback_reveal_queue_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:1100px;">
    <div class="mb-4">
        <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm mb-2">{{ __('pages.back') }}</a>
        <h1 class="page-title mb-1">{{ __('pages.feedback_reveal_queue_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('pages.feedback_reveal_queue_desc') }}</p>
    </div>

    <div class="app-card card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('pages.requester') }}</th>
                        <th>{{ __('pages.feedback_survey') }}</th>
                        <th>{{ __('pages.feedback_anonymous_response_short') }}</th>
                        <th>{{ __('pages.reason') }}</th>
                        <th>{{ __('pages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pending as $item)
                        <tr>
                            <td>{{ $item->requester?->displayName() ?? '—' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->survey?->title }}</div>
                                <small class="text-muted">{{ $item->survey?->course?->title }} · {{ $item->survey?->module?->title }}</small>
                            </td>
                            <td>#{{ $item->submission_id }}</td>
                            <td class="small" style="max-width:280px;">{{ $item->reason }}</td>
                            <td class="d-flex gap-2 flex-wrap">
                                <form method="POST" action="{{ route('superadmin.feedback-reveal.approve', $item) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success">{{ __('pages.approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.feedback-reveal.deny', $item) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger">{{ __('pages.deny') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('pages.feedback_reveal_queue_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $pending->links() }}
</div>
@endsection
