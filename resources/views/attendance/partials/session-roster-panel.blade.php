@if(! empty($group['roster']['rows']))
    @include('attendance.partials.session-roster-actions', [
        'session' => $group['session'],
        'roster' => $group['roster'],
    ])

    <div class="px-3 py-2 border-bottom bg-light small d-flex flex-wrap gap-3">
        <span>{{ __('pages.roster_enrolled') }}: <strong>{{ $group['roster']['enrolled'] }}</strong></span>
        <span>{{ __('pages.roster_recorded') }}: <strong>{{ $group['roster']['recorded'] }}</strong></span>
        <span class="{{ $group['roster']['missing'] > 0 ? 'text-danger' : '' }}">
            {{ __('pages.roster_missing') }}: <strong>{{ $group['roster']['missing'] }}</strong>
        </span>
    </div>

    @include('attendance.partials.session-roster-table', [
        'session' => $group['session'],
        'rows' => $group['roster']['rows'],
    ])
@else
    @include('attendance.partials.group-records-by-status', [
        'records' => $group['records'],
        'showSessionColumn' => false,
        'showDateColumn' => false,
    ])
@endif
