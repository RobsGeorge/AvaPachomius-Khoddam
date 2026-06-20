<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap">{{ __('pages.user') }}</th>
                @if($showSessionColumn ?? false)
                    <th class="text-nowrap">{{ __('pages.lecture') }}</th>
                @endif
                @if($showDateColumn ?? false)
                    <th class="text-nowrap">{{ __('pages.date') }}</th>
                @endif
                @if($showStatusColumn ?? true)
                    <th class="text-nowrap">{{ __('pages.status') }}</th>
                @endif
                <th class="text-nowrap">{{ __('pages.permission_reason') }}</th>
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
                    @if($showSessionColumn ?? false)
                        <td>{{ $record->session?->session_title ?? __('pages.unspecified') }}</td>
                    @endif
                    @if($showDateColumn ?? false)
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
                    @if($showStatusColumn ?? true)
                        <td class="text-nowrap">
                            <select class="status-select form-select form-select-sm"
                                    data-attendance-id="{{ $record->attendance_id }}"
                                    data-current-status="{{ $record->status }}"
                                    onchange="updateStatus(this)">
                                <option value="Present" {{ $record->status === 'Present' ? 'selected' : '' }}>{{ __('pages.present') }}</option>
                                <option value="Absent" {{ $record->status === 'Absent' ? 'selected' : '' }}>{{ __('pages.absent') }}</option>
                                <option value="Late" {{ $record->status === 'Late' ? 'selected' : '' }}>{{ __('pages.late') }}</option>
                                <option value="Permission" {{ $record->status === 'Permission' ? 'selected' : '' }}>{{ __('pages.permission') }}</option>
                            </select>
                        </td>
                    @endif
                    <td style="min-width:140px;">
                        <div id="permission-reason-{{ $record->attendance_id }}" class="{{ $record->status === 'Permission' ? '' : 'd-none' }}">
                            <input type="text"
                                   class="permission-reason form-control form-control-sm"
                                   placeholder="{{ __('pages.permission_reason') }}"
                                   value="{{ $record->permission_reason }}"
                                   onchange="updatePermissionReason(this, {{ $record->attendance_id }})">
                        </div>
                    </td>
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
