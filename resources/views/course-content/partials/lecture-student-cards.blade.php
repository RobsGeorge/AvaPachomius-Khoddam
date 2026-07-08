@props(['lectures', 'showWeek' => false])

<div class="lecture-list-compact">
    @foreach($lectures as $i => $lecture)
        <article class="lecture-compact-item">
            <div class="lecture-compact-main">
                <span class="lecture-compact-index" aria-hidden="true">{{ $i + 1 }}</span>
                <div class="lecture-compact-body">
                    <h3 class="lecture-compact-title mb-0">{{ $lecture->title }}</h3>
                    @if(($showWeek && $lecture->week_number) || $lecture->lecture_date)
                        <div class="lecture-compact-meta">
                            @if($showWeek && $lecture->week_number)
                                <span class="badge rounded-pill text-bg-secondary">{{ __('pages.week') }} {{ $lecture->week_number }}</span>
                            @endif
                            @if($lecture->lecture_date)
                                <span class="text-muted-theme small">{{ $lecture->lecture_date->format('Y-m-d') }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if($lecture->slides_link)
                <a href="{{ $lecture->slides_link }}" target="_blank" rel="noopener noreferrer"
                   class="btn btn-sm btn-primary lecture-compact-slides">
                    <i class="bi bi-file-earmark-slides-fill"></i>
                    <span>{{ __('pages.download_slides') }}</span>
                </a>
            @endif
        </article>
    @endforeach
</div>
