@props(['name', 'label', 'value' => null])

<div class="mb-3">
    <label class="form-label fw-semibold">{{ $label }}</label>
    <div class="star-rating d-flex flex-row-reverse justify-content-end gap-1">
        @for($i = 5; $i >= 1; $i--)
            <input type="radio" id="{{ $name }}_{{ $i }}" name="{{ $name }}" value="{{ $i }}"
                   @checked(old($name, $value) == $i)>
            <label for="{{ $name }}_{{ $i }}" title="{{ $i }}">
                <i class="bi bi-star-fill"></i>
            </label>
        @endfor
    </div>
</div>

@once
@push('styles')
<style>
    .star-rating input { display: none; }
    .star-rating label {
        font-size: 1.5rem;
        color: var(--bs-secondary-color, #cbd5e0);
        cursor: pointer;
        margin: 0;
    }
    .star-rating label:hover,
    .star-rating label:hover ~ label,
    .star-rating input:checked ~ label {
        color: #f6ad55;
    }
</style>
@endpush
@endonce
