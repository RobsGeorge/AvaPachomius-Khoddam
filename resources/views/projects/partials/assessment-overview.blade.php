@php
    $stats = $stats ?? null;
@endphp
@if($stats)
    @php
        $fillPercent = $stats['capacity'] > 0
            ? min(100, (int) round($stats['seated'] / $stats['capacity'] * 100))
            : 0;
    @endphp
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
                <div class="small text-muted">{{ __('projects.overview_seated') }}</div>
                <div class="fw-semibold">{{ $stats['seated'] }} / {{ $stats['capacity'] }}</div>
                <div class="progress mt-1" style="height: 4px;" role="progressbar"
                     aria-label="{{ __('projects.overview_seated') }}"
                     aria-valuenow="{{ $fillPercent }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: {{ $fillPercent }}%;"></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
                <div class="small text-muted">{{ __('projects.overview_teams') }}</div>
                <div class="fw-semibold">{{ $stats['teams'] }}</div>
                <div class="small text-muted">
                    {{ __('projects.overview_full', ['count' => $stats['full']]) }}
                    @if($stats['locked'] > 0)
                        · {{ __('projects.overview_locked', ['count' => $stats['locked']]) }}
                    @endif
                    @if($stats['cancelled'] > 0)
                        · {{ __('projects.overview_cancelled', ['count' => $stats['cancelled']]) }}
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
                <div class="small text-muted">{{ __('projects.overview_deliverables') }}</div>
                <div class="fw-semibold">
                    @if($stats['required_deliverables'] > 0)
                        {{ $stats['required_deliverables'] - $stats['missing_deliverables'] }} / {{ $stats['required_deliverables'] }}
                    @else
                        —
                    @endif
                </div>
                @if($stats['missing_deliverables'] > 0)
                    <div class="small text-warning-emphasis">
                        {{ __('projects.overview_missing', ['count' => $stats['missing_deliverables']]) }}
                    </div>
                @endif
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
                <div class="small text-muted">{{ __('projects.overview_graded') }}</div>
                <div class="fw-semibold">{{ $stats['graded_teams'] }} / {{ $stats['teams'] }}</div>
                <div class="small text-muted">
                    @if($assessment->areResultsAnnounced())
                        {{ __('projects.overview_announced') }}
                    @else
                        {{ __('projects.overview_not_announced') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($stats['below_minimum'] > 0)
        <p class="small text-warning-emphasis">
            {{ __('projects.overview_below_minimum', ['count' => $stats['below_minimum']]) }}
        </p>
    @endif
@endif
