@props(['lectures', 'course'])

<div class="table-responsive">
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
                                  onsubmit="return confirm(@json(__('pages.confirm_delete_lecture')))">
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
