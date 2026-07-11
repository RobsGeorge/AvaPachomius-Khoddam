<div class="modal fade" id="edit-step-{{ $step->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.courses.application-form.steps.update', [$courseModel->course_id, $step]) }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">{{ __('pages.edit') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.title') }}</label>
                    <input type="text" name="title" class="form-control" value="{{ $step->title }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.description') }}</label>
                    <textarea name="description" rows="2" class="form-control">{{ $step->description }}</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
            </div>
        </form>
    </div>
</div>
