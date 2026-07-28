@extends('layouts.app')

@section('title', __('people_onboarding.create_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width: 720px;">
    <h1 class="page-title mb-3">{{ __('people_onboarding.create_title') }}</h1>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('people.store') }}" class="app-card card shadow-sm">
        @csrf
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">{{ __('people_onboarding.first_name') }}</label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('people_onboarding.second_name') }}</label>
                <input type="text" name="second_name" value="{{ old('second_name') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('people_onboarding.third_name') }}</label>
                <input type="text" name="third_name" value="{{ old('third_name') }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('people_onboarding.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('people_onboarding.mobile_number') }}</label>
                <input type="text" name="mobile_number" value="{{ old('mobile_number') }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('people_onboarding.national_id') }}</label>
                <input type="text" name="national_id" value="{{ old('national_id') }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('people_onboarding.date_of_birth') }}</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('people_onboarding.service') }}</label>
                <select name="service_id" class="form-select">
                    <option value="">—</option>
                    @foreach($services as $service)
                        <option value="{{ $service->service_id }}" @selected(old('service_id') == $service->service_id)>
                            {{ $service->title_en ?: $service->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="invite_now" value="1" id="invite_now" @checked(old('invite_now', $defaultInvite))>
                    <label class="form-check-label" for="invite_now">{{ __('people_onboarding.invite_now') }}</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="send_email" value="1" id="send_email" @checked(old('send_email', true))>
                    <label class="form-check-label" for="send_email">{{ __('people_onboarding.bulk_invite_email') }}</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="send_whatsapp" value="1" id="send_whatsapp" @checked(old('send_whatsapp'))>
                    <label class="form-check-label" for="send_whatsapp">{{ __('people_onboarding.bulk_invite_whatsapp') }}</label>
                </div>
                @if(session('duplicate_matches'))
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="confirm_duplicate" value="1" id="confirm_duplicate">
                        <label class="form-check-label" for="confirm_duplicate">{{ __('people_onboarding.confirm_duplicate') }}</label>
                    </div>
                @endif
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">{{ __('people_onboarding.save') }}</button>
                <a href="{{ route('people.index') }}" class="btn btn-link">{{ __('people_onboarding.hub_title') }}</a>
            </div>
        </div>
    </form>
</div>
@endsection
