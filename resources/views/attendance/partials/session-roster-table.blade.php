<div class="table-responsive d-none d-lg-block admin-table-desktop">
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
                        @include('attendance.partials.session-roster-status-controls', [
                            'session' => $session,
                            'student' => $student,
                            'attendance' => $attendance,
                        ])
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

<div class="d-lg-none admin-data-cards student-data-hub">
    @forelse($rows as $row)
        @php
            $student = $row['user'];
            $attendance = $row['attendance'] ?? null;
            $isMissing = $row['missing'] ?? ($attendance === null);
        @endphp
        <article class="data-card {{ $isMissing ? 'border-warning' : '' }}">
            <div class="data-card-title">
                <a href="{{ route('attendance.user', $student->user_id) }}" class="text-decoration-none">
                    {{ trim($student->first_name . ' ' . $student->second_name . ' ' . ($student->third_name ?? '')) }}
                </a>
            </div>
            <dl class="data-meta-list mb-2">
                <div class="data-meta-row">
                    <dt>{{ __('pages.status') }}</dt>
                    <dd>
                        @include('attendance.partials.session-roster-status-controls', [
                            'session' => $session,
                            'student' => $student,
                            'attendance' => $attendance,
                        ])
                    </dd>
                </div>
                @if($attendance?->takenBy)
                    <div class="data-meta-row">
                        <dt>{{ __('pages.recorded_by') }}</dt>
                        <dd>{{ $attendance->takenBy->first_name . ' ' . $attendance->takenBy->second_name }}</dd>
                    </div>
                @endif
                @if($attendance?->display_attendance_time)
                    <div class="data-meta-row">
                        <dt>{{ __('pages.recorded_at') }}</dt>
                        <dd>{{ $attendance->display_attendance_time }}</dd>
                    </div>
                @endif
            </dl>
        </article>
    @empty
        <p class="text-center text-muted-theme py-4 mb-0">{{ __('pages.no_students_enrolled_in_course') }}</p>
    @endforelse
</div>
