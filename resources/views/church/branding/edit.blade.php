@extends('layouts.app')

@section('title', __('public_site.branding_edit_title'))

@section('content')
<div class="container py-3" style="max-width:720px;">
    <h1 class="page-title mb-1">{{ __('public_site.branding_edit_title') }}</h1>
    <p class="text-muted-theme mb-3">{{ __('public_site.branding_edit_intro') }}</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="post" action="{{ route('church.branding.update') }}" enctype="multipart/form-data" class="row g-3">
        @csrf
        @method('PUT')

        <div class="col-12">
            <label class="form-label">{{ __('public_site.palette') }}</label>
            <select name="palette" id="branding_palette" class="form-select">
                @foreach($palettes as $key => $colors)
                    <option value="{{ $key }}" @selected(old('palette', $branding['palette']) === $key)>
                        {{ __('public_site.palette_'.$key) }}
                    </option>
                @endforeach
                <option value="custom" @selected(old('palette', $branding['palette']) === 'custom')>
                    {{ __('public_site.palette_custom') }}
                </option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">{{ __('public_site.primary') }}</label>
            <input type="color" name="primary" class="form-control form-control-color w-100"
                   value="{{ old('primary', $branding['primary']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('public_site.accent') }}</label>
            <input type="color" name="accent" class="form-control form-control-color w-100"
                   value="{{ old('accent', $branding['accent']) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('public_site.primary_text') }}</label>
            <input type="color" name="primary_text" class="form-control form-control-color w-100"
                   value="{{ old('primary_text', $branding['primary_text']) }}">
        </div>

        <div class="col-md-6">
            <label class="form-label">{{ __('public_site.font_display') }}</label>
            <select name="font_display" class="form-select">
                @foreach($fonts as $font)
                    <option value="{{ $font }}" @selected(old('font_display', $branding['font_display']) === $font)>
                        {{ __('public_site.font_'.$font) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('public_site.font_body') }}</label>
            <select name="font_body" class="form-select">
                @foreach($fonts as $font)
                    <option value="{{ $font }}" @selected(old('font_body', $branding['font_body']) === $font)>
                        {{ __('public_site.font_'.$font) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="apply_to_portal" value="0">
                <input class="form-check-input" type="checkbox" name="apply_to_portal" value="1" id="apply_to_portal"
                    @checked(old('apply_to_portal', $branding['apply_to_portal'] ?? true))>
                <label class="form-check-label" for="apply_to_portal">{{ __('public_site.apply_to_portal') }}</label>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label">{{ __('public_site.logo') }}</label>
            @if($logoUrl)
                <div class="mb-2">
                    <img src="{{ $logoUrl }}" alt="" style="max-height:64px;" class="border rounded p-1 bg-white">
                </div>
                <div class="form-check mb-2">
                    <input type="hidden" name="clear_logo" value="0">
                    <input class="form-check-input" type="checkbox" name="clear_logo" value="1" id="clear_logo">
                    <label class="form-check-label" for="clear_logo">{{ __('public_site.clear_logo') }}</label>
                </div>
            @endif
            <input type="file" name="logo" class="form-control" accept="image/*">
            <div class="form-text">{{ __('public_site.logo_hint') }}</div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('public_site.branding_save') }}</button>
            <a href="{{ route('public.church.profile') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">{{ __('public_site.view_public') }}</a>
            <a href="{{ route('church.public-profile.edit') }}" class="btn btn-outline-secondary">{{ __('public_site.profile_edit_title') }}</a>
        </div>
    </form>
</div>
@endsection
