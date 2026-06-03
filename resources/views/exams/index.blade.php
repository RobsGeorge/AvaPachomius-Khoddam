@extends('layouts.app')

@section('title', __('pages.exams_schedule_results'))

@section('content')
<div class="container py-4 animate-in">
    <div class="app-card card">
        <div class="card-body">
            <h2 class="page-title mb-4">{{ __('pages.exams_schedule_results') }}</h2>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.exam_name') }}</th>
                            <th>{{ __('pages.module') }}</th>
                            <th>{{ __('pages.duration') }}</th>
                            <th>{{ __('pages.exam_date') }}</th>
                            <th>{{ __('pages.done') }}?</th>
                            <th>{{ __('pages.resources') }}</th>
                            <th>{{ __('pages.my_grades') }}</th>
                            <th>{{ __('pages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($exams as $exam)
                            @foreach($exam->schedules as $schedule)
                                <tr>
                                    <td>{{ $exam->exam_name }}</td>
                                    <td>{{ $exam->module->title ?? '—' }}</td>
                                    <td>{{ $exam->duration_minutes }} {{ __('pages.minutes') }}</td>
                                    <td>{{ $schedule->scheduled_date->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if($schedule->is_completed)
                                            <span class="badge bg-success">{{ __('pages.done') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ __('pages.not_done') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $exam->study_resources }}</td>
                                    <td>
                                        @php
                                            $result = $exam->results->where('schedule_id', $schedule->schedule_id)->first();
                                        @endphp
                                        {{ $result ? $result->score : '-' }}
                                    </td>
                                    <td></td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function selectSchedule(scheduleId) {
    alert(@json(__('pages.reschedule_success')));
}
</script>
@endpush
@endsection
