@extends('layouts.app')

@section('title', __('communications.title'))

@section('content')
<div class="container py-4 animate-in student-data-hub">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('communications.title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('communications.subtitle') }}</p>
        </div>
        <a class="btn btn-outline-primary"
           href="{{ route('communications.report.export', request()->query()) }}">
            <i class="bi bi-download"></i> {{ __('communications.export_csv') }}
        </a>
    </div>

    <div class="alert alert-info small">{{ __('communications.open_tracking_note') }}</div>
    <div class="alert alert-secondary small">{{ __('communications.sms_note') }}</div>

    <form method="get" action="{{ route('communications.report') }}" class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="q">{{ __('communications.filter_search') }}</label>
                    <input type="text" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="user_id">{{ __('communications.filter_person') }}</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">{{ __('communications.filter_person_any') }}</option>
                        @foreach($people as $person)
                            <option value="{{ $person->user_id }}" @selected((string)($filters['user_id'] ?? '') === (string)$person->user_id)>
                                {{ $person->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="channel">{{ __('communications.filter_channel') }}</label>
                    <select class="form-select" id="channel" name="channel">
                        <option value="">{{ __('communications.filter_channel_any') }}</option>
                        @foreach($channels as $channel)
                            <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>
                                {{ __('communications.channels.'.$channel) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">{{ __('communications.filter_status') }}</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">{{ __('communications.filter_status_any') }}</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                {{ __('communications.statuses.'.$status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="opened">{{ __('communications.filter_opened') }}</label>
                    <select class="form-select" id="opened" name="opened">
                        <option value="">{{ __('communications.filter_opened_any') }}</option>
                        <option value="yes" @selected(($filters['opened'] ?? '') === 'yes')>{{ __('communications.filter_opened_yes') }}</option>
                        <option value="no" @selected(($filters['opened'] ?? '') === 'no')>{{ __('communications.filter_opened_no') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="course_id">{{ __('communications.filter_course') }}</label>
                    <select class="form-select" id="course_id" name="course_id">
                        <option value="">{{ __('communications.filter_course_any') }}</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->course_id }}" @selected((string)($filters['course_id'] ?? '') === (string)$course->course_id)>
                                {{ $course->localizedTitle() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="service_id">{{ __('communications.filter_service') }}</label>
                    <select class="form-select" id="service_id" name="service_id">
                        <option value="">{{ __('communications.filter_service_any') }}</option>
                        @foreach($services as $service)
                            <option value="{{ $service->service_id }}" @selected((string)($filters['service_id'] ?? '') === (string)$service->service_id)>
                                {{ $service->localizedTitle() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="month">{{ __('communications.filter_month') }}</label>
                    <input type="month" class="form-control" id="month" name="month" value="{{ $filters['month'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="date_from">{{ __('communications.filter_date_from') }}</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="date_to">{{ __('communications.filter_date_to') }}</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('communications.apply_filters') }}</button>
                    <a href="{{ route('communications.report') }}" class="btn btn-outline-secondary">{{ __('communications.reset_filters') }}</a>
                </div>
            </div>
        </div>
    </form>

    <div class="table-responsive d-none d-lg-block admin-table-desktop app-card card shadow-sm">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('communications.col_sent_at') }}</th>
                    <th>{{ __('communications.col_person') }}</th>
                    <th>{{ __('communications.col_channel') }}</th>
                    <th>{{ __('communications.col_destination') }}</th>
                    <th>{{ __('communications.col_subject') }}</th>
                    <th>{{ __('communications.col_status') }}</th>
                    <th>{{ __('communications.col_opened') }}</th>
                    <th>{{ __('communications.col_read') }}</th>
                    <th>{{ __('communications.col_course') }}</th>
                    <th>{{ __('communications.col_service') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->sent_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $log->recipient_name ?? $log->user?->displayName() ?? '—' }}</td>
                        <td>{{ __('communications.channels.'.$log->channel) }}</td>
                        <td dir="ltr" class="text-break">{{ $log->destination() ?? '—' }}</td>
                        <td>{{ $log->subject ?? '—' }}</td>
                        <td>
                            {{ __('communications.statuses.'.$log->status) }}
                            @if($log->failure_reason)
                                <div class="small text-danger">{{ $log->failure_reason }}</div>
                            @endif
                        </td>
                        <td>{{ $log->opened_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $log->read_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $log->course?->localizedTitle() ?? '—' }}</td>
                        <td>{{ $log->service?->localizedTitle() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">{{ __('communications.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-lg-none admin-data-cards student-data-hub">
        @forelse($logs as $log)
            <article class="data-card app-card card shadow-sm mb-3">
                <div class="card-body">
                    <div class="data-card-title">{{ $log->recipient_name ?? $log->user?->displayName() ?? '—' }}</div>
                    <dl class="data-meta-list mb-0">
                        <div class="data-meta-row">
                            <dt>{{ __('communications.col_sent_at') }}</dt>
                            <dd>{{ $log->sent_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('communications.col_channel') }}</dt>
                            <dd>{{ __('communications.channels.'.$log->channel) }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('communications.col_destination') }}</dt>
                            <dd dir="ltr">{{ $log->destination() ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('communications.col_subject') }}</dt>
                            <dd>{{ $log->subject ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('communications.col_status') }}</dt>
                            <dd>{{ __('communications.statuses.'.$log->status) }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('communications.col_opened') }}</dt>
                            <dd>{{ $log->opened_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('communications.col_read') }}</dt>
                            <dd>{{ $log->read_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </article>
        @empty
            <p class="text-muted">{{ __('communications.empty') }}</p>
        @endforelse
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
