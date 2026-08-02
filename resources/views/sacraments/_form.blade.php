@php
    /** @var \App\Models\Sacrament|null $sacrament */
    $typeValue = old('type', $sacrament?->type);
    $dateValue = old('date', $sacrament?->date?->format('Y-m-d'));
    $precisionValue = old('date_precision', $sacrament?->date_precision ?? 'day');
@endphp
<div class="card-body row g-3">
    <div class="col-md-6">
        <label class="form-label" for="type">{{ __('sacraments.fields.type') }}</label>
        <select name="type" id="type" class="form-select" required>
            @foreach($types as $type)
                <option value="{{ $type }}" @selected($typeValue === $type)>{{ __('sacraments.types.'.$type) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="date_precision">{{ __('sacraments.fields.date_precision') }}</label>
        <select name="date_precision" id="date_precision" class="form-select" required>
            @foreach(\App\Models\Sacrament::PRECISIONS as $precision)
                <option value="{{ $precision }}" @selected($precisionValue === $precision)>
                    {{ __('sacraments.precision.'.$precision) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="date">{{ __('sacraments.fields.date') }}</label>
        <input type="date" name="date" id="date" value="{{ $dateValue }}" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="location_text">{{ __('sacraments.fields.location_text') }}</label>
        <input type="text" name="location_text" id="location_text" value="{{ old('location_text', $sacrament?->location_text) }}" class="form-control" maxlength="255">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="location_church_id">{{ __('sacraments.fields.location_church_id') }}</label>
        <input type="number" name="location_church_id" id="location_church_id" value="{{ old('location_church_id', $sacrament?->location_church_id) }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="officiant_person_id">{{ __('sacraments.fields.officiant_person_id') }}</label>
        <input type="number" name="officiant_person_id" id="officiant_person_id" value="{{ old('officiant_person_id', $sacrament?->officiant_person_id) }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="second_person_id">{{ __('sacraments.fields.second_person_id') }}</label>
        <input type="number" name="second_person_id" id="second_person_id" value="{{ old('second_person_id', $sacrament?->second_person_id) }}" class="form-control">
    </div>
</div>
