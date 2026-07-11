<div class="modal fade" id="add-field-{{ $step->id }}" tabindex="-1" aria-labelledby="add-field-{{ $step->id }}-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.courses.application-form.fields.store', [$courseModel->course_id, $step]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="add-field-{{ $step->id }}-label">{{ __('course_applications.add_field') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                </div>
                <div class="modal-body">
                    @include('admin.course-application-forms.partials.field-form', ['field' => null, 'fieldTypes' => $fieldTypes])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($step->fields as $field)
    <div class="modal fade" id="edit-field-{{ $field->id }}" tabindex="-1" aria-labelledby="edit-field-{{ $field->id }}-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.courses.application-form.fields.update', [$courseModel->course_id, $field]) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="edit-field-{{ $field->id }}-label">{{ __('pages.edit') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.course-application-forms.partials.field-form', ['field' => $field, 'fieldTypes' => $fieldTypes])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
