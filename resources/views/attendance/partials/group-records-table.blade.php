@php
    $showSessionColumn = $showSessionColumn ?? false;
    $showDateColumn = $showDateColumn ?? false;
    $showStatusColumn = $showStatusColumn ?? true;
    $showPermissionReasonColumn = $showPermissionReasonColumn ?? false;
@endphp

<div class="table-responsive d-none d-lg-block admin-table-desktop">
    <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap">{{ __('pages.user') }}</th>
                @if($showSessionColumn)
                    <th class="text-nowrap">{{ __('pages.lecture') }}</th>
                @endif
                @if($showDateColumn)
                    <th class="text-nowrap">{{ __('pages.date') }}</th>
                @endif
                @if($showStatusColumn)
                    <th class="text-nowrap">{{ __('pages.status') }}</th>
                @endif
                @if($showPermissionReasonColumn)
                    <th class="text-nowrap">{{ __('pages.permission_reason') }}</th>
                @endif
                <th class="text-nowrap">{{ __('pages.recorded_by') }}</th>
                <th class="text-nowrap">{{ __('pages.recorded_at') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr>
                    <td class="text-nowrap">
                        @if($record->user)
                            <a href="{{ route('attendance.user', $record->user_id) }}">
                                {{ trim($record->user->first_name . ' ' . $record->user->second_name . ' ' . ($record->user->third_name ?? '')) }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    @if($showSessionColumn)
                        <td>{{ $record->session?->session_title ?? __('pages.unspecified') }}</td>
                    @endif
                    @if($showDateColumn)
                        <td class="text-nowrap">
                            @if($record->display_session_date)
                                <a href="{{ route('attendance.by-date', $record->display_session_date) }}">
                                    {{ $record->display_session_date }}
                                </a>
                            @else
                                {{ __('pages.unspecified') }}
                            @endif
                        </td>
                    @endif
                    @if($showStatusColumn)
                        <td class="text-nowrap">
                            @include('attendance.partials.group-records-status-controls', [
                                'record' => $record,
                                'showPermissionReasonColumn' => $showPermissionReasonColumn,
                            ])
                        </td>
                    @endif
                    @if($showPermissionReasonColumn)
                        <td style="min-width:140px;">
                            <div id="permission-reason-{{ $record->attendance_id }}">
                                <input type="text"
                                       class="permission-reason form-control form-control-sm"
                                       placeholder="{{ __('pages.permission_reason') }}"
                                       value="{{ $record->permission_reason }}"
                                       data-attendance-id="{{ $record->attendance_id }}"
                                       onchange="updatePermissionReason(this)">
                            </div>
                        </td>
                    @endif
                    <td class="text-nowrap">
                        @if($record->takenBy)
                            {{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-nowrap">{{ $record->display_attendance_time ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="d-lg-none admin-data-cards student-data-hub">
    @foreach($records as $record)
        <article class="data-card">
            <div class="data-card-title">
                @if($record->user)
                    <a href="{{ route('attendance.user', $record->user_id) }}" class="text-decoration-none">
                        {{ trim($record->user->first_name . ' ' . $record->user->second_name . ' ' . ($record->user->third_name ?? '')) }}
                    </a>
                @else
                    —
                @endif
            </div>
            <dl class="data-meta-list mb-0">
                @if($showSessionColumn)
                    <div class="data-meta-row">
                        <dt>{{ __('pages.lecture') }}</dt>
                        <dd>{{ $record->session?->session_title ?? __('pages.unspecified') }}</dd>
                    </div>
                @endif
                @if($showDateColumn && $record->display_session_date)
                    <div class="data-meta-row">
                        <dt>{{ __('pages.date') }}</dt>
                        <dd>
                            <a href="{{ route('attendance.by-date', $record->display_session_date) }}">
                                {{ $record->display_session_date }}
                            </a>
                        </dd>
                    </div>
                @endif
                @if($showStatusColumn)
                    <div class="data-meta-row">
                        <dt>{{ __('pages.status') }}</dt>
                        <dd>
                            @include('attendance.partials.group-records-status-controls', [
                                'record' => $record,
                                'showPermissionReasonColumn' => $showPermissionReasonColumn,
                            ])
                        </dd>
                    </div>
                @endif
                @if($showPermissionReasonColumn)
                    <div class="data-meta-row">
                        <dt>{{ __('pages.permission_reason') }}</dt>
                        <dd>
                            <div id="permission-reason-{{ $record->attendance_id }}">
                                <input type="text"
                                       class="permission-reason form-control form-control-sm"
                                       placeholder="{{ __('pages.permission_reason') }}"
                                       value="{{ $record->permission_reason }}"
                                       data-attendance-id="{{ $record->attendance_id }}"
                                       onchange="updatePermissionReason(this)">
                            </div>
                        </dd>
                    </div>
                @endif
                @if($record->takenBy)
                    <div class="data-meta-row">
                        <dt>{{ __('pages.recorded_by') }}</dt>
                        <dd>{{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}</dd>
                    </div>
                @endif
                @if($record->display_attendance_time)
                    <div class="data-meta-row">
                        <dt>{{ __('pages.recorded_at') }}</dt>
                        <dd>{{ $record->display_attendance_time }}</dd>
                    </div>
                @endif
            </dl>
        </article>
    @endforeach
</div>
