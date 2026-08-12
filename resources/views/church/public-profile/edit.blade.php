@extends('layouts.app')
@section('title', __('public_site.profile_edit_title'))
@section('content')
<div class="container py-4" style="max-width:900px;">
    <h1 class="page-title mb-1">{{ __('public_site.profile_edit_title') }}</h1>
    <p class="text-muted-theme mb-3">{{ __('public_site.profile_edit_intro') }}</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="post" action="{{ route('church.public-profile.update') }}" class="app-card card shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">{{ __('public_site.tagline_ar') }}</label>
                <input type="text" name="tagline[ar]" class="form-control" value="{{ old('tagline.ar', $profile['tagline']['ar']) }}" maxlength="255">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('public_site.tagline_en') }}</label>
                <input type="text" name="tagline[en]" class="form-control" value="{{ old('tagline.en', $profile['tagline']['en']) }}" maxlength="255">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('public_site.about_ar') }}</label>
                <textarea name="about[ar]" class="form-control" rows="4" maxlength="5000">{{ old('about.ar', $profile['about']['ar']) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('public_site.about_en') }}</label>
                <textarea name="about[en]" class="form-control" rows="4" maxlength="5000">{{ old('about.en', $profile['about']['en']) }}</textarea>
            </div>
            <div class="col-md-8">
                <label class="form-label">{{ __('public_site.address') }}</label>
                <input type="text" name="address" class="form-control" value="{{ old('address', $profile['address']) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('public_site.city') }}</label>
                <input type="text" name="city" class="form-control" value="{{ old('city', $profile['city']) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('public_site.geo_lat') }}</label>
                <input type="number" step="any" name="geo[lat]" class="form-control" value="{{ old('geo.lat', $profile['geo']['lat']) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('public_site.geo_lng') }}</label>
                <input type="number" step="any" name="geo[lng]" class="form-control" value="{{ old('geo.lng', $profile['geo']['lng']) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('public_site.phone') }}</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $profile['phone']) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('public_site.whatsapp') }}</label>
                <input type="text" name="whatsapp" class="form-control" value="{{ old('whatsapp', $profile['whatsapp']) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('public_site.email') }}</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $profile['email']) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Facebook</label>
                <input type="url" name="social[facebook]" class="form-control" value="{{ old('social.facebook', $profile['social']['facebook']) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">YouTube</label>
                <input type="url" name="social[youtube]" class="form-control" value="{{ old('social.youtube', $profile['social']['youtube']) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Instagram</label>
                <input type="url" name="social[instagram]" class="form-control" value="{{ old('social.instagram', $profile['social']['instagram']) }}">
            </div>

            <div class="col-12"><hr><h2 class="h6">{{ __('public_site.liturgy_hours') }}</h2></div>
            @php $hours = old('liturgy_hours', collect($profile['liturgy_hours'])->map(fn ($h) => [
                'day' => $h['day'] ?? '',
                'time_ar' => $h['time']['ar'] ?? '',
                'time_en' => $h['time']['en'] ?? '',
            ])->all()); if (count($hours) < 3) { $hours = array_pad($hours, 3, ['day'=>'','time_ar'=>'','time_en'=>'']); } @endphp
            @foreach($hours as $i => $row)
                <div class="col-md-4">
                    <input type="text" name="liturgy_hours[{{ $i }}][day]" class="form-control" placeholder="{{ __('public_site.day') }}" value="{{ $row['day'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <input type="text" name="liturgy_hours[{{ $i }}][time_ar]" class="form-control" placeholder="{{ __('public_site.time_ar') }}" value="{{ $row['time_ar'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <input type="text" name="liturgy_hours[{{ $i }}][time_en]" class="form-control" placeholder="{{ __('public_site.time_en') }}" value="{{ $row['time_en'] ?? '' }}">
                </div>
            @endforeach

            <div class="col-12"><hr><h2 class="h6">{{ __('public_site.visibility') }}</h2></div>
            @foreach(['tagline','about','address','contact','social','liturgy_hours'] as $group)
                <div class="col-md-4">
                    <div class="form-check">
                        <input type="hidden" name="show_on_public_site[{{ $group }}]" value="0">
                        <input class="form-check-input" type="checkbox" name="show_on_public_site[{{ $group }}]" value="1" id="show_{{ $group }}"
                            @checked(old("show_on_public_site.{$group}", $profile['show_on_public_site'][$group] ?? true))>
                        <label class="form-check-label" for="show_{{ $group }}">{{ __('public_site.show_'.$group) }}</label>
                    </div>
                </div>
            @endforeach

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('public_site.save') }}</button>
                <a href="{{ route('public.church.profile') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">{{ __('public_site.view_public') }}</a>
            </div>
        </div>
    </form>
</div>
@endsection
