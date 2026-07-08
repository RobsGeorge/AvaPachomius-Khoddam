@props(['lectures', 'course'])

<div class="table-responsive d-none d-lg-block admin-table-desktop">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:50px;">#</th>
                <th style="width:80px;">{{ __('pages.week_col') }}</th>
                <th style="width:110px;">{{ __('pages.date') }}</th>
                <th>{{ __('pages.lecture') }}</th>
                <th style="width:70px;">{{ __('pages.video_col') }}</th>
                <th style="width:70px;">{{ __('pages.slides_col') }}</th>
                <th>{{ __('pages.extra_materials_col') }}</th>
                <th style="width:110px;">{{ __('pages.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lectures as $i => $lecture)
                <tr>
                    <td class="text-muted small">{{ $i + 1 }}</td>
                    <td><span class="badge bg-secondary">{{ $lecture->week_number }}</span></td>
                    <td class="small text-muted">
                        {{ $lecture->lecture_date ? $lecture->lecture_date->format('Y-m-d') : '—' }}
                    </td>
                    <td class="fw-semibold">
                        {{ $lecture->title }}
                        @if($lecture->notes)
                            <i class="bi bi-sticky text-warning ms-1" title="{{ $lecture->notes }}"></i>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($lecture->video_link)
                            <a href="{{ $lecture->video_link }}" target="_blank"
                               class="btn btn-sm btn-outline-danger py-0 px-1">
                                <i class="bi bi-play-fill"></i>
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($lecture->slides_link)
                            <a href="{{ $lecture->slides_link }}" target="_blank"
                               class="btn btn-sm btn-outline-primary py-0 px-1">
                                <i class="bi bi-file-earmark-slides"></i>
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="text-muted-theme small">{{ __('pages.links_count', ['count' => $lecture->materials->count()]) }}</span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('lectures.edit', $lecture->lecture_id) }}"
                               class="btn btn-sm btn-outline-primary py-0 px-2" title="{{ __('pages.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST"
                                  action="{{ route('lectures.destroy', $lecture->lecture_id) }}"
                                  data-confirm="{{ __('pages.confirm_delete_lecture') }}"
                                  onsubmit="return confirm(this.dataset.confirm)">
                                @csrf @method('DELETE')
                                <input type="hidden" name="course_id" value="{{ $course->course_id }}">
                                <button class="btn btn-sm btn-outline-danger py-0 px-2" title="{{ __('pages.delete') }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="d-lg-none admin-data-cards lecture-admin-cards px-2 py-2">
    @foreach($lectures as $i => $lecture)
        <div class="card shadow-sm mb-2 border-0 bg-light-subtle">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <span class="badge bg-secondary me-1">#{{ $i + 1 }}</span>
                        <span class="badge bg-secondary">{{ __('pages.week_col') }} {{ $lecture->week_number }}</span>
                    </div>
                    <span class="small text-muted">
                        {{ $lecture->lecture_date ? $lecture->lecture_date->format('Y-m-d') : '—' }}
                    </span>
                </div>
                <div class="fw-semibold mb-2">
                    {{ $lecture->title }}
                    @if($lecture->notes)
                        <i class="bi bi-sticky text-warning ms-1" title="{{ $lecture->notes }}"></i>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @if($lecture->video_link)
                        <a href="{{ $lecture->video_link }}" target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-play-fill"></i> {{ __('pages.video_col') }}
                        </a>
                    @endif
                    @if($lecture->slides_link)
                        <a href="{{ $lecture->slides_link }}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-file-earmark-slides"></i> {{ __('pages.slides_col') }}
                        </a>
                    @endif
                    <span class="badge bg-white text-dark border align-self-center">
                        {{ __('pages.links_count', ['count' => $lecture->materials->count()]) }}
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('lectures.edit', $lecture->lecture_id) }}"
                       class="btn btn-sm btn-outline-primary flex-grow-1">
                        <i class="bi bi-pencil"></i> {{ __('pages.edit') }}
                    </a>
                    <form method="POST"
                          action="{{ route('lectures.destroy', $lecture->lecture_id) }}"
                          class="flex-grow-1"
                          data-confirm="{{ __('pages.confirm_delete_lecture') }}"
                          onsubmit="return confirm(this.dataset.confirm)">
                        @csrf @method('DELETE')
                        <input type="hidden" name="course_id" value="{{ $course->course_id }}">
                        <button class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-trash"></i> {{ __('pages.delete') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>
