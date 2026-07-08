@props(['lectures', 'showWeek' => false])

<div class="lecture-list-compact">
    @foreach($lectures as $i => $lecture)
        @php
            $hasVideo = filled($lecture->video_link);
            $hasSlides = filled($lecture->slides_link);
            $notesText = trim((string) ($lecture->notes ?? ''));
            $hasNotes = $notesText !== '';
            $extraMaterials = $lecture->materials->filter(fn ($material) => filled($material->link));
            $hasLinks = $hasVideo || $hasSlides || $extraMaterials->isNotEmpty();
            $hasMaterials = $hasLinks || $hasNotes;
            $notesPreviewLimit = 160;
            $notesIsLong = $hasNotes && mb_strlen($notesText) > $notesPreviewLimit;
            $notesPreview = $notesIsLong
                ? mb_substr($notesText, 0, $notesPreviewLimit) . '…'
                : $notesText;
        @endphp
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

            @if($hasMaterials)
                <div class="lecture-compact-materials">
                    @if($hasLinks)
                        <div class="lecture-compact-actions">
                            @if($hasVideo)
                                <a href="{{ $lecture->video_link }}" target="_blank" rel="noopener noreferrer"
                                   class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-play-circle-fill"></i>
                                    <span>{{ __('pages.watch_video') }}</span>
                                </a>
                            @endif
                            @if($hasSlides)
                                <a href="{{ $lecture->slides_link }}" target="_blank" rel="noopener noreferrer"
                                   class="btn btn-sm btn-primary">
                                    <i class="bi bi-file-earmark-slides-fill"></i>
                                    <span>{{ __('pages.download_slides') }}</span>
                                </a>
                            @endif
                            @foreach($extraMaterials as $material)
                                <a href="{{ $material->link }}" target="_blank" rel="noopener noreferrer"
                                   class="btn btn-sm btn-outline-theme"
                                   title="{{ $material->link }}">
                                    <i class="bi bi-link-45deg"></i>
                                    <span>{{ $material->title ?: __('pages.additional_materials') }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if($hasNotes)
                        <div class="lecture-compact-notes">
                            <div class="lecture-compact-notes-label">{{ __('pages.notes') }}</div>
                            <div class="lecture-compact-notes-body">{{ $notesPreview }}</div>
                            @if($notesIsLong)
                                <button type="button"
                                        class="btn btn-link btn-sm px-0 lecture-compact-notes-more"
                                        data-bs-toggle="modal"
                                        data-bs-target="#lecture-notes-{{ $lecture->lecture_id }}">
                                    {{ __('pages.more') }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </article>

        @if($hasNotes && $notesIsLong)
            <div class="modal fade" id="lecture-notes-{{ $lecture->lecture_id }}" tabindex="-1"
                 aria-labelledby="lecture-notes-title-{{ $lecture->lecture_id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="lecture-notes-title-{{ $lecture->lecture_id }}">{{ $lecture->title }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                        </div>
                        <div class="modal-body lecture-compact-notes-body">{{ $notesText }}</div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>
