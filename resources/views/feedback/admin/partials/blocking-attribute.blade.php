@php
    $blockingSelected = filter_var(
        old('is_mandatory', $isMandatory ?? true),
        FILTER_VALIDATE_BOOLEAN
    );
    $selectedBlock = old('blocked_assessment', $blockedAssessment ?? '');
    $assessments = $moduleAssessments ?? [];
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
    <p class="form-text mb-2">{{ __('pages.feedback_blocking_help') }}</p>
    <div id="blocked-assessment-wrap" class="{{ $blockingSelected ? '' : 'd-none' }}">
        <label class="form-label" for="blocked_assessment">{{ __('pages.feedback_blocked_assessment') }}</label>
        <select name="blocked_assessment" id="blocked_assessment"
                class="form-select @error('blocked_assessment') is-invalid @enderror"
                @if($blockingSelected) required @endif>
            <option value="">{{ __('pages.feedback_blocked_assessment_placeholder') }}</option>
            @foreach($assessments as $row)
                <option value="{{ $row['key'] }}"
                        data-module="{{ $row['module_id'] }}"
                        @selected($selectedBlock === $row['key'])>
                    {{ $row['label'] }}
                </option>
            @endforeach
        </select>
        @error('blocked_assessment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <p class="form-text mb-0">{{ __('pages.feedback_blocked_assessment_help') }}</p>
    </div>
</fieldset>
