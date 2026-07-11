<div class="modal fade" id="edit-step-{{ $step->id }}" tabindex="-1" aria-labelledby="edit-step-{{ $step->id }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.courses.application-form.steps.update', [$courseModel->course_id, $step]) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="edit-step-{{ $step->id }}-label">{{ __('pages.edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit-step-title-{{ $step->id }}">{{ __('pages.title') }}</label>
                        <input type="text" name="title" id="edit-step-title-{{ $step->id }}" class="form-control" value="{{ $step->title }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit-step-description-{{ $step->id }}">{{ __('pages.description') }}</label>
                        <textarea name="description" id="edit-step-description-{{ $step->id }}" rows="2" class="form-control">{{ $step->description }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
