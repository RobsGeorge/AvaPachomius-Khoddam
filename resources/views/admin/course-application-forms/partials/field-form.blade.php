@php
    use App\Models\CourseApplicationFormField;
    $config = $field?->config ?? [];
    $options = $config['options'] ?? [['value' => '', 'label' => '']];
@endphp
<div class="row g-3">
    @if(! $field)
        <div class="col-md-6">
            <label class="form-label">{{ __('course_applications.field_type') }}</label>
            <select name="type" class="form-select" required>
                @foreach($fieldTypes as $type)
                    <option value="{{ $type }}">{{ __('course_applications.field_types.'.$type) }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-md-6">
        <label class="form-label">{{ __('course_applications.field_label') }}</label>
        <input type="text" name="label" class="form-control" value="{{ $field?->label }}" required>
    </div>
    <div class="col-12">
        <label class="form-label">{{ __('course_applications.field_help') }}</label>
        <textarea name="help_text" rows="2" class="form-control">{{ $field?->help_text }}</textarea>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="required" value="1" id="required-{{ $field?->id ?? 'new' }}"
                   @checked($field?->required)>
            <label class="form-check-label" for="required-{{ $field?->id ?? 'new' }}">{{ __('course_applications.field_required') }}</label>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">max_length</label>
        <input type="number" name="config[max_length]" class="form-control" value="{{ $config['max_length'] ?? '' }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">max_size_kb</label>
        <input type="number" name="config[max_size_kb]" class="form-control" value="{{ $config['max_size_kb'] ?? 5120 }}">
    </div>
    <div class="col-12">
        <label class="form-label">{{ __('course_applications.field_types.dropdown') }} / {{ __('course_applications.field_types.single_choice') }}</label>
        @for($i = 0; $i < max(2, count($options)); $i++)
            <div class="row g-2 mb-2">
                <div class="col-md-5">
                    <input type="text" name="config[options][{{ $i }}][value]" class="form-control" placeholder="value"
                           value="{{ $options[$i]['value'] ?? '' }}">
                </div>
                <div class="col-md-7">
                    <input type="text" name="config[options][{{ $i }}][label]" class="form-control" placeholder="label"
                           value="{{ $options[$i]['label'] ?? '' }}">
                </div>
            </div>
        @endfor
    </div>
</div>
