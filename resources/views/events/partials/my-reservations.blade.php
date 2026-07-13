<div class="student-data-hub">
    @forelse($reservations as $r)
        <article class="data-card app-card card shadow-sm mb-3">
            <div class="card-body">
                <div class="data-card-title">{{ $r->event?->title }}</div>
                <dl class="data-meta-list mb-0">
                    <div class="data-meta-row">
                        <dt>{{ __('events.status') }}</dt>
                        <dd>{{ __('events.status_'.$r->status) }}</dd>
                    </div>
                    <div class="data-meta-row">
                        <dt>{{ __('events.reserved_at') }}</dt>
                        <dd>{{ $r->reserved_at?->timezone(config('attendance.timezone'))->format('Y-m-d H:i') }}</dd>
                    </div>
                </dl>
                @if($r->isActive() && $r->event)
                    <div class="data-card-actions">
                        <a href="{{ route('events.show', $r->event_id) }}" class="btn btn-sm btn-outline-theme">{{ __('events.view') }}</a>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <p class="text-muted-theme">{{ __('events.none_visible') }}</p>
    @endforelse

    {{ $reservations->links() }}
</div>
