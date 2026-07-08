@props(['lectures', 'showWeek' => false])

<div class="student-data-hub p-2 p-md-3">
    @foreach($lectures as $i => $lecture)
        <article class="data-card">
            <div class="data-card-title">
                <span class="text-muted small me-1">#{{ $i + 1 }}</span>
                {{ $lecture->title }}
            </div>
            <dl class="data-meta-list mb-0">
                @if($showWeek && $lecture->week_number)
                    <div class="data-meta-row">
                        <dt>{{ __('pages.week_col') }}</dt>
                        <dd><span class="badge bg-secondary rounded-pill">{{ __('pages.week') }} {{ $lecture->week_number }}</span></dd>
                    </div>
                @endif
                <div class="data-meta-row">
                    <dt>{{ __('pages.date') }}</dt>
                    <dd>{{ $lecture->lecture_date ? $lecture->lecture_date->format('Y-m-d') : '—' }}</dd>
                </div>
                <div class="data-meta-row">
                    <dt>{{ __('pages.video_col') }}</dt>
                    <dd>
                        @if($lecture->video_link)
                            <a href="{{ $lecture->video_link }}" target="_blank" class="btn btn-sm btn-danger">
                                <i class="bi bi-play-fill"></i> {{ __('pages.watch_video') }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>
                </div>
                <div class="data-meta-row">
                    <dt>{{ __('pages.slides') }}</dt>
                    <dd>
                        @if($lecture->slides_link)
                            <a href="{{ $lecture->slides_link }}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="bi bi-file-earmark-slides-fill"></i> {{ __('pages.download_slides') }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>
                </div>
                <div class="data-meta-row">
                    <dt>{{ __('pages.additional_materials') }}</dt>
                    <dd>
                        @forelse($lecture->materials as $mat)
                            <a href="{{ $mat->link }}" target="_blank"
                               class="badge bg-light text-primary border text-decoration-none me-1 mb-1 d-inline-block"
                               title="{{ $mat->link }}">
                                <i class="bi bi-link-45deg"></i> {{ $mat->title }}
                            </a>
                        @empty
                            <span class="text-muted small">—</span>
                        @endforelse
                    </dd>
                </div>
                <div class="data-meta-row">
                    <dt>{{ __('pages.notes') }}</dt>
                    <dd>
                        @if($lecture->notes)
                            <span class="text-muted small" style="white-space:pre-line;">{{ Str::limit($lecture->notes, 120) }}</span>
                            @if(strlen($lecture->notes) > 120)
                                <a href="#" data-bs-toggle="modal" data-bs-target="#notes-{{ $lecture->lecture_id }}" class="small d-block">{{ __('pages.more') }}</a>
                                <div class="modal fade" id="notes-{{ $lecture->lecture_id }}" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">{{ $lecture->title }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body" style="white-space:pre-line;">{{ $lecture->notes }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </article>
    @endforeach
</div>
