@extends('layouts.app')

@section('title', __('event_theme.title'))

@section('content')
<div class="container py-3" style="max-width:720px;">
    <h1 class="page-title mb-1">{{ __('event_theme.title') }}</h1>
    <p class="text-muted-theme mb-3">{{ __('event_theme.intro') }}</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="app-card card mb-3">
        <div class="card-body d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-calendar-heart fs-4 app-icon"></i>
            <span class="fw-semibold">{{ __('event_theme.status_label') }}:</span>
            @if($active)
                <span class="badge bg-danger">{{ __('event_theme.status_active') }}</span>
            @else
                <span class="badge bg-secondary">{{ __('event_theme.status_inactive') }}</span>
            @endif
        </div>
    </div>

    <form method="post" action="{{ route('church.event-theme.update') }}" x-data="eventThemePeriods()" class="row g-3">
        @csrf
        @method('PUT')

        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="enabled_manual" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="enabled_manual" value="1" id="enabled_manual"
                    @checked(old('enabled_manual', $config['enabled_manual']))>
                <label class="form-check-label" for="enabled_manual">{{ __('event_theme.manual_label') }}</label>
            </div>
            <div class="form-text">{{ __('event_theme.manual_hint') }}</div>
        </div>

        <div class="col-12">
            <label class="form-label mb-1">{{ __('event_theme.periods_title') }}</label>
            <p class="form-text mt-0 mb-2">{{ __('event_theme.periods_hint') }}</p>

            <template x-for="(p, i) in periods" :key="i">
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">{{ __('event_theme.start') }}</label>
                        <input type="date" class="form-control" :name="`periods[${i}][start]`" x-model="p.start">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">{{ __('event_theme.end') }}</label>
                        <input type="date" class="form-control" :name="`periods[${i}][end]`" x-model="p.end">
                    </div>
                    <div class="col-9 col-md-5">
                        <label class="form-label small mb-1">{{ __('event_theme.period_label') }}</label>
                        <input type="text" class="form-control" maxlength="80" :name="`periods[${i}][label]`" x-model="p.label"
                               placeholder="{{ __('event_theme.label_placeholder') }}">
                    </div>
                    <div class="col-3 col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100" @click="periods.splice(i, 1)"
                                aria-label="{{ __('event_theme.remove') }}"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </template>

            <button type="button" class="btn btn-outline-theme btn-sm" @click="add()"
                    x-show="periods.length < {{ \App\Support\EventTheme::MAX_PERIODS }}">
                <i class="bi bi-plus-lg"></i> {{ __('event_theme.add_period') }}
            </button>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">{{ __('event_theme.save') }}</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function eventThemePeriods() {
        return {
            periods: @json(old('periods', array_values($config['periods'] ?? []))),
            add() {
                if (this.periods.length < {{ \App\Support\EventTheme::MAX_PERIODS }}) {
                    this.periods.push({ start: '', end: '', label: '' });
                }
            },
        };
    }
</script>
@endpush
@endsection
