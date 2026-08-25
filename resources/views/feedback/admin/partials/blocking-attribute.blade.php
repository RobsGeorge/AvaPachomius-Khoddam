@php
    $blockingSelected = filter_var(
        old('is_mandatory', $isMandatory ?? true),
        FILTER_VALIDATE_BOOLEAN
    );
@endphp
<fieldset class="mb-3">
    <legend class="form-label fs-6 mb-2">{{ __('pages.feedback_blocking_attribute') }}</legend>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="is_mandatory" value="1" id="survey_blocking"
               @checked($blockingSelected) required>
        <label class="form-check-label" for="survey_blocking">{{ __('pages.feedback_blocking_label') }}</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="is_mandatory" value="0" id="survey_non_blocking"
               @checked(! $blockingSelected)>
        <label class="form-check-label" for="survey_non_blocking">{{ __('pages.feedback_non_blocking_label') }}</label>
    </div>
    <p class="form-text mb-0">{{ __('pages.feedback_blocking_help') }}</p>
</fieldset>
