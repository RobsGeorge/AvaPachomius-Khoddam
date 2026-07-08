@props(['question', 'value' => null, 'readonly' => false, 'namePrefix' => 'answers'])

@php
    $fieldName = $namePrefix.'['.$question->question_id.']';
    $fieldId = 'q_'.$question->question_id;
@endphp

<div class="mb-4 pb-3 border-bottom">
    <label class="form-label fw-semibold" for="{{ $fieldId }}">
        {{ $question->scopeLabel() }}
        @if($question->is_required)<span class="text-danger">*</span>@endif
    </label>
    @if($question->help_text)
        <div class="form-text mb-2">{{ $question->help_text }}</div>
    @endif

    @if($readonly)
        <div class="p-3 bg-light rounded">{{ $value ?? '—' }}</div>
    @elseif($question->question_type === 'rating')
        <div class="d-flex gap-1 flex-row-reverse justify-content-end star-rating">
            @for($i = $question->ratingMax(); $i >= 1; $i--)
                <input type="radio" id="{{ $fieldId }}_{{ $i }}" name="{{ $fieldName }}" value="{{ $i }}"
                       @checked(old($fieldName, $value) == $i) @disabled($readonly)>
                <label for="{{ $fieldId }}_{{ $i }}"><i class="bi bi-star-fill"></i></label>
            @endfor
        </div>
    @elseif($question->question_type === 'slider')
        <input type="range" class="form-range" id="{{ $fieldId }}" name="{{ $fieldName }}"
               min="{{ $question->sliderMin() }}" max="{{ $question->sliderMax() }}"
               value="{{ old($fieldName, $value ?? $question->sliderMin()) }}"
               oninput="document.getElementById('{{ $fieldId }}_val').textContent = this.value">
        <div class="small text-muted">{{ __('pages.selected_value') }}: <span id="{{ $fieldId }}_val">{{ old($fieldName, $value ?? $question->sliderMin()) }}</span></div>
    @elseif($question->question_type === 'mcq')
        @foreach($question->choices() as $idx => $choice)
            <div class="form-check">
                <input class="form-check-input" type="radio" name="{{ $fieldName }}" id="{{ $fieldId }}_{{ $idx }}"
                       value="{{ $choice }}" @checked(old($fieldName, $value) === $choice)>
                <label class="form-check-label" for="{{ $fieldId }}_{{ $idx }}">{{ $choice }}</label>
            </div>
        @endforeach
    @else
        <textarea class="form-control" id="{{ $fieldId }}" name="{{ $fieldName }}" rows="3"
                  placeholder="{{ __('pages.your_answer') }}">{{ old($fieldName, $value) }}</textarea>
    @endif
</div>

@once
@push('styles')
<style>
    .star-rating input { display: none; }
    .star-rating label { font-size: 1.5rem; color: #cbd5e0; cursor: pointer; margin: 0; }
    .star-rating label:hover, .star-rating label:hover ~ label, .star-rating input:checked ~ label { color: #f6ad55; }
</style>
@endpush
@endonce
