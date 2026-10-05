@if(! empty($group['roster']['needs_lectures']))
    <div class="alert alert-warning m-3 mb-0">{{ __('pages.attendance_needs_lectures') }}</div>
@else
@if(! empty($group['roster']['lectures']) && $group['roster']['lectures']->count() > 0 && ($group['session']?->usesLectureAttendance()))
    <div class="px-3 py-3 border-bottom">
        <div class="small fw-semibold mb-2">{{ __('pages.attendance_lecture_switch') }}</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach($group['roster']['lectures'] as $lectureOption)
                <a href="{{ route('attendance.all', array_merge(request()->except('page'), ['filter_by' => 'session', 'session_id' => $group['session']->session_id, 'lecture_id' => $lectureOption->lecture_id])) }}"
                   class="btn btn-sm {{ (int) ($group['roster']['lecture']?->lecture_id) === (int) $lectureOption->lecture_id ? 'btn-primary' : 'btn-outline-theme' }}">
                    {{ $lectureOption->title }}
                </a>
            @endforeach
        </div>
    </div>
@endif

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
        'lectureId' => $group['roster']['lecture']?->lecture_id,
    ])
@else
    @include('attendance.partials.group-records-by-status', [
        'records' => $group['records'],
        'showSessionColumn' => false,
        'showDateColumn' => false,
    ])
@endif
@endif
