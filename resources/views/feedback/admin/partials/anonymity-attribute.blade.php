@php
    $anonymousSelected = filter_var(
        old('is_anonymous', $isAnonymous ?? true),
        FILTER_VALIDATE_BOOLEAN
    );
@endphp
<fieldset class="mb-3">
    <legend class="form-label fs-6 mb-2">{{ __('pages.feedback_anonymity_attribute') }}</legend>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="is_anonymous" value="1" id="survey_anonymous"
               @checked($anonymousSelected) required>
        <label class="form-check-label" for="survey_anonymous">{{ __('pages.feedback_anonymous_label') }}</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="is_anonymous" value="0" id="survey_identified"
               @checked(! $anonymousSelected)>
        <label class="form-check-label" for="survey_identified">{{ __('pages.feedback_identified_label') }}</label>
    </div>
    <p class="form-text mb-0">{{ __('pages.feedback_anonymity_help') }}</p>
</fieldset>
