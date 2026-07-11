@php
    use App\Models\CourseApplicationFormField;
    $choiceTypes = [
        CourseApplicationFormField::TYPE_SINGLE_CHOICE,
        CourseApplicationFormField::TYPE_DROPDOWN,
        CourseApplicationFormField::TYPE_MULTISELECT,
        CourseApplicationFormField::TYPE_CHECKBOX_GROUP,
    ];
@endphp

<div class="modal fade" id="add-field-{{ $step->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('admin.courses.application-form.fields.store', [$courseModel->course_id, $step]) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('course_applications.add_field') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @include('admin.course-application-forms.partials.field-form', ['field' => null, 'fieldTypes' => $fieldTypes])
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
            </div>
        </form>
    </div>
</div>

@foreach($step->fields as $field)
    <div class="modal fade" id="edit-field-{{ $field->id }}" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('admin.courses.application-form.fields.update', [$courseModel->course_id, $field]) }}" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('pages.edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('admin.course-application-forms.partials.field-form', ['field' => $field, 'fieldTypes' => $fieldTypes])
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
