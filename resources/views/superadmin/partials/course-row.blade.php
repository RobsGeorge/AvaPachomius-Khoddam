<tr>
    @unless($requiresChurch)
        <td class="text-muted-theme small">
            {{ $course->church?->name ?? $course->service?->church?->name ?? '—' }}
        </td>
    @endunless
    <td @if($requiresChurch) class="ps-5" @endif>
        <div class="fw-semibold">{{ $course->title }}</div>
        <div class="text-muted-theme small text-truncate" style="max-width:240px;" title="{{ $course->description }}">
            {{ $course->description }}
        </div>
    </td>
    <td>{{ $course->service?->localizedTitle() ?? '—' }}</td>
    <td>{{ $course->year }}</td>
    <td>
        <form method="POST"
              action="{{ route('superadmin.courses.destroy', $course->course_id) }}"
              data-confirm="{{ __('pages.confirm_delete_course') }}">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    </td>
</tr>
