<article class="data-card mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
        <div>
            <div class="data-card-title mb-1">{{ $item->title }}</div>
            <small class="text-muted-theme">
                @if($item->isFinished())
                    {{ __('announcements.finished_status') }}
                @elseif($item->isPublished())
                    {{ __('announcements.published_status') }}
                @else
                    {{ __('announcements.draft') }}
                @endif
                @if($item->course) · {{ $item->course->title }} @endif
                @if($item->banner_ends_at)
                    · {{ __('announcements.ends_on', ['date' => $item->banner_ends_at->format('d/m/Y H:i')]) }}
                @endif
            </small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('announcements.manage.edit', $item) }}" class="btn btn-sm btn-outline-primary">{{ __('announcements.edit') }}</a>
            @if($item->isPublished())
                <a href="{{ route('announcements.manage.directory', $item) }}" class="btn btn-sm btn-outline-secondary">{{ __('announcements.directory') }}</a>
                <form method="POST" action="{{ route('announcements.manage.unpublish', $item) }}"
                      data-confirm="{{ __('announcements.unpublish').'?' }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('announcements.unpublish') }}</button>
                </form>
            @endif
            <form method="POST" action="{{ route('announcements.manage.clone', $item) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('announcements.clone') }}</button>
            </form>
        </div>
    </div>
    <p class="mb-0 text-muted-theme">{{ \Illuminate\Support\Str::limit($item->body, 180) }}</p>
</article>
