@php
    use App\Models\CourseApplicationFormField;
    $name = 'fields.'.$field->field_key;
    $value = old($name, $snapshot[$field->field_key] ?? null);
    $hasError = $errors->has($name);
    $isRejected = in_array($field->field_key, $rejectedFields ?? [], true);
    $comment = $rejectedComments[$field->field_key]->comment ?? null;
@endphp

@if($field->type === CourseApplicationFormField::TYPE_SECTION_HEADING)
    <div class="col-12">
        <h3 class="h5 mb-1">{{ $field->label }}</h3>
        @if($field->help_text)
            <p class="text-muted-theme small">{{ $field->help_text }}</p>
        @endif
    </div>
@elseif($field->type === CourseApplicationFormField::TYPE_PARAGRAPH)
    <div class="col-12">
        <p class="mb-0">{{ $field->label }}</p>
        @if($field->help_text)
            <p class="text-muted-theme small">{{ $field->help_text }}</p>
        @endif
    </div>
@else
    <div class="col-md-6">
        <label class="form-label" for="field-{{ $field->field_key }}">
            {{ $field->label }}
            @if($field->required)<span class="text-danger">*</span>@endif
        </label>

        @switch($field->type)
            @case(CourseApplicationFormField::TYPE_LONG_TEXT)
                <textarea id="field-{{ $field->field_key }}" name="{{ $name }}" rows="4"
                          class="form-control @if($hasError) is-invalid @endif @if($isRejected) border-danger @endif">{{ $value }}</textarea>
                @break
            @case(CourseApplicationFormField::TYPE_SINGLE_CHOICE)
                @foreach(($field->config['options'] ?? []) as $option)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="{{ $name }}" id="{{ $field->field_key }}-{{ $option['value'] }}"
                               value="{{ $option['value'] }}" @checked($value == $option['value'])>
                        <label class="form-check-label" for="{{ $field->field_key }}-{{ $option['value'] }}">{{ $option['label'] }}</label>
                    </div>
                @endforeach
                @break
            @case(CourseApplicationFormField::TYPE_DROPDOWN)
                <select id="field-{{ $field->field_key }}" name="{{ $name }}" class="form-select @if($hasError) is-invalid @endif">
                    <option value="">{{ __('pages.select_option') }}</option>
                    @foreach(($field->config['options'] ?? []) as $option)
                        <option value="{{ $option['value'] }}" @selected($value == $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @break
            @case(CourseApplicationFormField::TYPE_MULTISELECT)
            @case(CourseApplicationFormField::TYPE_CHECKBOX_GROUP)
                @php $selected = is_array($value) ? $value : []; @endphp
                @foreach(($field->config['options'] ?? []) as $option)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="{{ $name }}[]"
                               id="{{ $field->field_key }}-{{ $option['value'] }}" value="{{ $option['value'] }}"
                               @checked(in_array($option['value'], $selected, true))>
                        <label class="form-check-label" for="{{ $field->field_key }}-{{ $option['value'] }}">{{ $option['label'] }}</label>
                    </div>
                @endforeach
                @break
            @case(CourseApplicationFormField::TYPE_CHECKBOX)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="field-{{ $field->field_key }}"
                           @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN))>
                    <label class="form-check-label" for="field-{{ $field->field_key }}">{{ $field->help_text ?: $field->label }}</label>
                </div>
                @break
            @case(CourseApplicationFormField::TYPE_FILE)
            @case(CourseApplicationFormField::TYPE_IMAGE)
                @if(filled($value))
                    <div class="small text-muted-theme mb-1">{{ basename($value) }}</div>
                @endif
                <input type="file" id="field-{{ $field->field_key }}" name="{{ $name }}"
                       class="form-control @if($hasError) is-invalid @endif">
                @break
            @default
                <input type="{{ match($field->type) {
                    CourseApplicationFormField::TYPE_EMAIL => 'email',
                    CourseApplicationFormField::TYPE_PHONE => 'tel',
                    CourseApplicationFormField::TYPE_URL => 'url',
                    CourseApplicationFormField::TYPE_NUMBER => 'number',
                    CourseApplicationFormField::TYPE_DATE => 'date',
                    default => 'text',
                } }}" id="field-{{ $field->field_key }}" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}"
                       class="form-control @if($hasError) is-invalid @endif @if($isRejected) border-danger @endif">
        @endswitch

        @if($field->help_text && ! in_array($field->type, [CourseApplicationFormField::TYPE_CHECKBOX, CourseApplicationFormField::TYPE_PARAGRAPH], true))
            <div class="form-text">{{ $field->help_text }}</div>
        @endif
        @if($comment)
            <div class="form-text text-danger">{{ $comment }}</div>
        @elseif($isRejected)
            <div class="form-text text-danger">{{ __('course_applications.field_rejected') }}</div>
        @endif
        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
@endif
