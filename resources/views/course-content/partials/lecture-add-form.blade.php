@props(['module', 'course', 'session'])

<form method="POST" action="{{ route('lectures.store') }}">
    @csrf
    <input type="hidden" name="module_id" value="{{ $module->module_id }}">
    <input type="hidden" name="course_id" value="{{ $course->course_id }}">
    <input type="hidden" name="session_id" value="{{ $session->session_id }}">

    <div class="row g-2 mb-2">
        <div class="col-md-5">
            <input type="text" name="title" class="form-control form-control-sm"
                   placeholder="{{ __('pages.lecture_title_placeholder') }}" maxlength="150" required>
        </div>
        <div class="col-md-2">
            <input type="date" name="lecture_date" class="form-control form-control-sm"
                   value="{{ $session->session_date?->format('Y-m-d') }}"
                   placeholder="{{ __('pages.date') }}">
        </div>
        <div class="col-md-2">
            <input type="number" name="order_index" class="form-control form-control-sm"
                   placeholder="{{ __('pages.sort_order_placeholder') }}" min="0" value="0">
        </div>
    </div>
    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <input type="url" name="video_link" class="form-control form-control-sm"
                   placeholder="{{ __('pages.video_url_optional') }}" maxlength="500">
        </div>
        <div class="col-md-4">
            <input type="url" name="slides_link" class="form-control form-control-sm"
                   placeholder="{{ __('pages.slides_url_optional') }}" maxlength="500">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-success btn-sm w-100">
                <i class="bi bi-plus-circle"></i> {{ __('pages.add') }}
            </button>
        </div>
    </div>
    <textarea name="notes" class="form-control form-control-sm"
              rows="2" placeholder="{{ __('pages.notes_optional') }}"></textarea>
</form>
