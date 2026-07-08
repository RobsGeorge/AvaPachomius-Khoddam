@extends('layouts.app')

@section('title', __('announcements.directory'))

@section('content')
<div class="container py-4 animate-in student-data-hub">
    <div class="mb-4">
        <a href="{{ route('announcements.manage.edit', $announcement) }}" class="text-decoration-none">&larr; {{ __('announcements.edit') }}</a>
        <h1 class="page-title mt-2 mb-1">{{ $announcement->title }}</h1>
        <p class="text-muted-theme mb-0">{{ __('announcements.directory') }}</p>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <p><strong>{{ __('announcements.published_status') }}:</strong> {{ $announcement->published_at?->format('d/m/Y H:i') ?? '—' }}</p>
            <p class="mb-0"><strong>{{ __('announcements.by', ['name' => $announcement->publisher?->displayName() ?? '—']) }}</strong></p>
        </div>
    </div>

    <div class="table-responsive d-none d-lg-block admin-table-desktop app-card card shadow-sm">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>{{ __('announcements.recipients') }}</th>
                    <th>{{ __('announcements.read') }}</th>
                    <th>{{ __('announcements.opened') }}</th>
                    <th>{{ __('announcements.dismissed') }}</th>
                    <th>{{ __('announcements.email_sent') }}</th>
                    <th>{{ __('announcements.whatsapp_sent') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($announcement->deliveries as $delivery)
                    <tr>
                        <td>{{ $delivery->user?->displayName() ?? '—' }}</td>
                        <td>{{ $delivery->read_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $delivery->opened_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $delivery->dismissed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $delivery->email_sent_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $delivery->whatsapp_sent_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-lg-none admin-data-cards student-data-hub">
        @foreach($announcement->deliveries as $delivery)
            <article class="data-card app-card card shadow-sm">
                <div class="card-body">
                    <div class="data-card-title">{{ $delivery->user?->displayName() ?? '—' }}</div>
                    <dl class="data-meta-list mb-0">
                        <div class="data-meta-row">
                            <dt>{{ __('announcements.read') }}</dt>
                            <dd>{{ $delivery->read_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('announcements.opened') }}</dt>
                            <dd>{{ $delivery->opened_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('announcements.dismissed') }}</dt>
                            <dd>{{ $delivery->dismissed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('announcements.email_sent') }}</dt>
                            <dd>{{ $delivery->email_sent_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('announcements.whatsapp_sent') }}</dt>
                            <dd>{{ $delivery->whatsapp_sent_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </article>
        @endforeach
    </div>
</div>
@endsection
