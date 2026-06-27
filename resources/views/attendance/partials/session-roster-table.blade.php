<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap">{{ __('pages.user') }}</th>
                <th class="text-nowrap">{{ __('pages.status') }}</th>
                <th class="text-nowrap">{{ __('pages.recorded_by') }}</th>
                <th class="text-nowrap">{{ __('pages.recorded_at') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                @php
                    /** @var \App\Models\User $student */
                    $student = $row['user'];
                    $attendance = $row['attendance'] ?? null;
                    $isMissing = $row['missing'] ?? ($attendance === null);
                @endphp
                <tr class="{{ $isMissing ? 'table-warning' : '' }}">
                    <td class="text-nowrap">
                        <a href="{{ route('attendance.user', $student->user_id) }}">
                            {{ trim($student->first_name . ' ' . $student->second_name . ' ' . ($student->third_name ?? '')) }}
                        </a>
                    </td>
                    <td class="text-nowrap" style="min-width: 160px;">
                        @if($attendance)
                            <select class="status-select form-select form-select-sm"
                                    data-attendance-id="{{ $attendance->attendance_id }}"
                                    data-current-status="{{ $attendance->status }}"
                                    onchange="updateStatus(this)">
                                <option value="Present" {{ $attendance->status === 'Present' ? 'selected' : '' }}>{{ __('pages.present') }}</option>
                                <option value="Absent" {{ $attendance->status === 'Absent' ? 'selected' : '' }}>{{ __('pages.absent') }}</option>
                                <option value="Late" {{ $attendance->status === 'Late' ? 'selected' : '' }}>{{ __('pages.late') }}</option>
                                <option value="Permission" {{ $attendance->status === 'Permission' ? 'selected' : '' }}>{{ __('pages.permission') }}</option>
                            </select>
                            <div id="permission-reason-{{ $attendance->attendance_id }}" class="mt-1 {{ $attendance->status === 'Permission' ? '' : 'd-none' }}">
                                <input type="text"
                                       class="permission-reason form-control form-control-sm"
                                       placeholder="{{ __('pages.permission_reason') }}"
                                       value="{{ $attendance->permission_reason }}"
                                       onchange="updatePermissionReason(this, {{ $attendance->attendance_id }})">
                            </div>
                        @else
                            <select class="form-select form-select-sm roster-status-select"
                                    data-session-id="{{ $session->session_id }}"
                                    data-user-id="{{ $student->user_id }}"
                                    onchange="setRosterStatus(this)">
                                <option value="" selected disabled>{{ __('pages.not_recorded') }}</option>
                                <option value="Present">{{ __('pages.present') }}</option>
                                <option value="Absent">{{ __('pages.absent') }}</option>
                                <option value="Late">{{ __('pages.late') }}</option>
                                <option value="Permission">{{ __('pages.permission') }}</option>
                            </select>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if($attendance?->takenBy)
                            {{ $attendance->takenBy->first_name . ' ' . $attendance->takenBy->second_name }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-nowrap">{{ $attendance?->display_attendance_time ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-muted-theme py-4">
                        {{ __('pages.no_students_enrolled_in_course') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
