@extends('layouts.app')

@section('title', __('notifications.settings_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('notifications.settings_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('notifications.settings_intro') }}</p>
        </div>
        <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if(! $whatsappConfigured)
        <div class="alert alert-warning">{{ __('notifications.whatsapp_not_configured') }}</div>
    @endif

    <form method="POST" action="{{ route('notifications.settings.update') }}">
        @csrf
        @method('PUT')

        @foreach($types as $type => $definition)
            @php($pref = $preferences[$type] ?? null)
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">{{ __($definition['label']) }}</h2>
                    @if(! empty($definition['mandatory']))
                        <p class="small text-muted-theme mb-2">{{ __('notifications.mandatory_notice') }}</p>
                    @endif
                    <div class="d-flex flex-wrap gap-3 mb-2">
                        <div class="form-check">
                            <input type="hidden" name="preferences[{{ $type }}][portal_enabled]" value="0">
                            <input class="form-check-input" type="checkbox" name="preferences[{{ $type }}][portal_enabled]" value="1" id="portal-{{ $type }}"
                                   @checked(old("preferences.{$type}.portal_enabled", $pref?->portal_enabled ?? true))>
                            <label class="form-check-label" for="portal-{{ $type }}">{{ __('notifications.channel_portal') }}</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="preferences[{{ $type }}][email_enabled]" value="0">
                            <input class="form-check-input" type="checkbox" name="preferences[{{ $type }}][email_enabled]" value="1" id="email-{{ $type }}"
                                   @checked(old("preferences.{$type}.email_enabled", $pref?->email_enabled ?? false))>
                            <label class="form-check-label" for="email-{{ $type }}">{{ __('notifications.channel_email') }}</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="preferences[{{ $type }}][whatsapp_enabled]" value="0">
                            <input class="form-check-input" type="checkbox" name="preferences[{{ $type }}][whatsapp_enabled]" value="1" id="whatsapp-{{ $type }}"
                                   @checked(old("preferences.{$type}.whatsapp_enabled", $pref?->whatsapp_enabled ?? false))>
                            <label class="form-check-label" for="whatsapp-{{ $type }}">{{ __('notifications.channel_whatsapp') }}</label>
                        </div>
                    </div>

                    @if($type === 'attendance_absent_streak')
                        <label class="form-label small">{{ __('notifications.config_sessions_lookback') }}</label>
                        <input type="number" min="1" max="20" class="form-control form-control-sm w-auto"
                               name="preferences[{{ $type }}][config][sessions_lookback]"
                               value="{{ old("preferences.{$type}.config.sessions_lookback", $pref?->config['sessions_lookback'] ?? 3) }}">
                    @endif
                    @if($type === 'session_unclosed')
                        <label class="form-label small">{{ __('notifications.config_unclosed_days') }}</label>
                        <input type="number" min="1" max="90" class="form-control form-control-sm w-auto"
                               name="preferences[{{ $type }}][config][unclosed_days]"
                               value="{{ old("preferences.{$type}.config.unclosed_days", $pref?->config['unclosed_days'] ?? 7) }}">
                    @endif
                    @if(in_array($type, ['assignment_deadline', 'exam_upcoming'], true))
                        <label class="form-label small">{{ __('notifications.config_lead_hours') }}</label>
                        <input type="number" min="1" max="168" class="form-control form-control-sm w-auto"
                               name="preferences[{{ $type }}][config][lead_hours]"
                               value="{{ old("preferences.{$type}.config.lead_hours", $pref?->config['lead_hours'] ?? 24) }}">
                    @endif
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
    </form>

    <div class="app-card card shadow-sm mt-4">
        <div class="card-header fw-semibold">{{ __('notifications.reminders_title') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('notifications.reminders.store') }}" class="row g-3 mb-4">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">{{ __('notifications.reminder_title') }}</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('notifications.reminder_when') }}</label>
                    <input type="datetime-local" name="remind_at" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('notifications.reminder_recurrence') }}</label>
                    <select name="recurrence" class="form-select">
                        <option value="once">{{ __('notifications.recurrence_once') }}</option>
                        <option value="daily">{{ __('notifications.recurrence_daily') }}</option>
                        <option value="weekly">{{ __('notifications.recurrence_weekly') }}</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('notifications.reminder_body') }}</label>
                    <textarea name="body" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12 d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="portal" value="1" id="reminder-portal" checked>
                        <label class="form-check-label" for="reminder-portal">{{ __('notifications.channel_portal') }}</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="email" value="1" id="reminder-email">
                        <label class="form-check-label" for="reminder-email">{{ __('notifications.channel_email') }}</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="whatsapp" value="1" id="reminder-whatsapp">
                        <label class="form-check-label" for="reminder-whatsapp">{{ __('notifications.channel_whatsapp') }}</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-primary">{{ __('notifications.reminder_create') }}</button>
                </div>
            </form>

            @forelse($reminders as $reminder)
                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                    <div>
                        <div class="fw-semibold">{{ $reminder->title }}</div>
                        <div class="small text-muted-theme">{{ $reminder->remind_at?->format('d/m/Y H:i') }} — {{ __('notifications.recurrence_'.$reminder->recurrence) }}</div>
                    </div>
                    <form method="POST" action="{{ route('notifications.reminders.destroy', $reminder) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('app.delete') }}</button>
                    </form>
                </div>
            @empty
                <p class="text-muted-theme mb-0">{{ __('notifications.no_notifications') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
