@php
    $statusOrder = ['Present', 'Absent', 'Late', 'Permission'];
    $statusBadges = [
        'Present' => 'bg-success',
        'Absent' => 'bg-danger',
        'Late' => 'bg-warning text-dark',
        'Permission' => 'bg-info text-dark',
    ];
    $recordsByStatus = $records->groupBy('status');
@endphp

@foreach($statusOrder as $status)
    @if($recordsByStatus->has($status) && $recordsByStatus[$status]->isNotEmpty())
        <div class="px-3 py-2 border-bottom bg-light d-flex align-items-center gap-2">
            <span class="badge {{ $statusBadges[$status] ?? 'bg-secondary' }}">
                {{ match ($status) {
                    'Present' => __('pages.present'),
                    'Absent' => __('pages.absent'),
                    'Late' => __('pages.late'),
                    'Permission' => __('pages.permission'),
                    default => $status,
                } }}
            </span>
            <span class="small text-muted-theme">
                {{ __('pages.records_in_group', ['count' => $recordsByStatus[$status]->count()]) }}
            </span>
        </div>
        @include('attendance.partials.group-records-table', [
            'records' => $recordsByStatus[$status],
            'showSessionColumn' => $showSessionColumn ?? false,
            'showDateColumn' => $showDateColumn ?? false,
            'showStatusColumn' => false,
        ])
    @endif
@endforeach
