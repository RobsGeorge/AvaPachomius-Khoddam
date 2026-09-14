@php
    $namePrefix = $namePrefix ?? '';
    $idPrefix = $idPrefix ?? ($namePrefix !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '-', $namePrefix) : 'team-brief');
    $values = $values ?? [];
    $compact = $compact ?? false;
    $controlClass = $compact ? 'form-control form-control-sm' : 'form-control';
    $keys = \App\Models\Project::BRIEF_KEYS;
    $fieldName = function (string $key) use ($namePrefix): string {
        return $namePrefix === '' ? $key : $namePrefix.'['.$key.']';
    };
    $fieldId = function (string $key) use ($idPrefix): string {
        return trim($idPrefix, '-').'-'.$key;
    };
@endphp
<div class="{{ $compact ? '' : 'border rounded p-3 bg-light-subtle' }}">
    <div class="fw-semibold {{ $compact ? 'small mb-1' : 'mb-1' }}">{{ __('projects.team_brief_heading') }}</div>
    <p class="small text-muted mb-2">{{ __('projects.team_brief_help') }} {{ __('projects.brief_max_help') }}</p>
    <div class="row g-2">
        @foreach($keys as $key)
            <div class="col-md-6">
                <label class="form-label small mb-1" for="{{ $fieldId($key) }}">{{ __('projects.'.$key) }}</label>
                <textarea name="{{ $fieldName($key) }}"
                          id="{{ $fieldId($key) }}"
                          class="{{ $controlClass }}"
                          rows="{{ $compact ? 2 : 3 }}"
                          maxlength="{{ \App\Models\Project::BRIEF_MAX_LENGTH }}"
                          placeholder="{{ __('projects.'.$key.'_placeholder') }}">{{ $values[$key] ?? '' }}</textarea>
            </div>
        @endforeach
    </div>
</div>
