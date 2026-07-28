@extends('layouts.app')

@section('title', __('tenancy.create_church'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <h1 class="page-title mb-2">{{ __('tenancy.create_church') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('tenancy.create_church_intro') }}</p>

    <form method="POST" action="{{ route('superadmin.churches.store') }}" class="app-card card shadow-sm" id="church-create-form">
        @csrf
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="name">{{ __('tenancy.col_name') }}</label>
                <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name') }}" required maxlength="120">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="form-label" for="short_name">{{ __('tenancy.col_short_name') }}</label>
                <input id="short_name" name="short_name" type="text" class="form-control @error('short_name') is-invalid @enderror"
                       value="{{ old('short_name') }}" required maxlength="40">
                <div class="form-text">{{ __('tenancy.short_name_hint') }}</div>
                @error('short_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @include('superadmin.churches._place_fields', ['church' => null, 'countries' => $countries])

            <div class="alert alert-light border mb-0" id="shown-name-preview" role="status">
                <strong>{{ __('tenancy.shown_name') }}:</strong>
                <span id="shown-name-value">—</span>
            </div>

            <div>
                <label class="form-label" for="slug">{{ __('tenancy.col_slug') }}</label>
                <div class="input-group">
                    <input id="slug" name="slug" type="text" class="form-control @error('slug') is-invalid @enderror"
                           value="{{ old('slug') }}" required maxlength="40" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                           placeholder="st-mark">
                    <button type="button" class="btn btn-outline-secondary" id="suggest-slug-btn">{{ __('tenancy.suggest_slug') }}</button>
                </div>
                <div class="form-text">{{ __('tenancy.slug_hint') }}</div>
                <div class="form-text" id="slug-suggestions"></div>
                @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="form-label" for="domain">{{ __('tenancy.custom_domain') }}</label>
                <input id="domain" name="domain" type="text" class="form-control @error('domain') is-invalid @enderror"
                       value="{{ old('domain') }}" maxlength="191" placeholder="optional.example.org">
                @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <fieldset>
                <legend class="form-label">{{ __('tenancy.capabilities') }}</legend>
                <div class="row g-2">
                    @foreach($capabilities as $key => $def)
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="capabilities[]"
                                       value="{{ $key }}" id="cap-{{ $key }}"
                                       @checked(collect(old('capabilities', array_keys($capabilities)))->contains($key))>
                                <label class="form-check-label" for="cap-{{ $key }}">{{ __($def['label']) }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label class="form-label" for="admin_user_ids">{{ __('tenancy.initial_admins') }}</label>
                <select id="admin_user_ids" name="admin_user_ids[]" class="form-select" multiple size="6">
                    @foreach($users as $user)
                        <option value="{{ $user->user_id }}" @selected(collect(old('admin_user_ids', []))->contains($user->user_id))>
                            {{ $user->email }} — {{ $user->first_name }} {{ $user->second_name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('tenancy.initial_admins_hint') }}</div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a href="{{ route('superadmin.churches.index') }}" class="btn btn-outline-secondary">{{ __('tenancy.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('tenancy.create_church') }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const suggestUrl = @json(route('superadmin.churches.suggest-slug'));
    const placeUrl = @json(route('superadmin.churches.search-places'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    function identityPayload() {
        return {
            name: document.getElementById('name')?.value || '',
            short_name: document.getElementById('short_name')?.value || '',
            place_country_code: document.getElementById('place_country_code')?.value || '',
            place_governorate: document.getElementById('place_governorate')?.value || '',
            place_district: document.getElementById('place_district')?.value || '',
        };
    }

    async function refreshSuggestions(applyFirst) {
        const params = new URLSearchParams(identityPayload());
        const res = await fetch(suggestUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        const shown = document.getElementById('shown-name-value');
        if (shown) shown.textContent = data.shown_name || '—';
        const box = document.getElementById('slug-suggestions');
        if (box) {
            box.innerHTML = '';
            (data.suggestions || []).forEach(function (s) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-primary me-1 mb-1';
                btn.textContent = s;
                btn.addEventListener('click', function () {
                    document.getElementById('slug').value = s;
                });
                box.appendChild(btn);
            });
        }
        if (applyFirst && data.suggestions && data.suggestions[0] && !document.getElementById('slug').value) {
            document.getElementById('slug').value = data.suggestions[0];
        }
    }

    document.getElementById('suggest-slug-btn')?.addEventListener('click', function () {
        refreshSuggestions(true);
    });

    ['name', 'short_name', 'place_country_code', 'place_governorate', 'place_district'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('change', function () { refreshSuggestions(false); });
    });

    const lookupBtn = document.getElementById('place-lookup-btn');
    const lookupQ = document.getElementById('place-lookup-q');
    const lookupResults = document.getElementById('place-lookup-results');
    lookupBtn?.addEventListener('click', async function () {
        const q = (lookupQ?.value || '').trim();
        if (q.length < 2) return;
        const country = document.getElementById('place_country_code')?.value || '';
        const params = new URLSearchParams({ q: q, country: country });
        const res = await fetch(placeUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
        if (!res.ok || !lookupResults) return;
        const data = await res.json();
        lookupResults.innerHTML = '';
        (data.results || []).forEach(function (row) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action';
            btn.textContent = row.label;
            btn.addEventListener('click', function () {
                document.getElementById('place_street').value = row.place_street || '';
                document.getElementById('place_district').value = row.place_district || '';
                document.getElementById('place_region').value = row.place_region || '';
                document.getElementById('place_governorate').value = row.place_governorate || '';
                if (row.place_country_code) {
                    document.getElementById('place_country_code').value = row.place_country_code;
                }
                lookupResults.innerHTML = '';
                refreshSuggestions(false);
            });
            lookupResults.appendChild(btn);
        });
    });

    refreshSuggestions(false);
})();
</script>
@endpush
