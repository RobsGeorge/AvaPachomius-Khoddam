@extends('layouts.app')

@section('title', __('registration_review.correction_title'))

@section('content')
@php
    use App\Models\RegistrationApplication;
    $rejectedComments = $application?->fieldReviews
        ->where('status', 'rejected')
        ->keyBy('field_key') ?? collect();
@endphp
<div class="container py-4 animate-in">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('registration_review.correction_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('registration_review.correction_intro') }}</p>
    </div>

<div class="app-card card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('application.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    @foreach(['first_name', 'second_name', 'third_name'] as $field)
                        <div class="col-md-4">
                            <label for="{{ $field }}" class="form-label">{{ __('registration_review.fields.'.$field) }}</label>
                            <input type="text" id="{{ $field }}" name="{{ $field }}"
                                   class="form-control @error($field) is-invalid @enderror @if(in_array($field, $rejectedFields, true)) border-danger @endif"
                                   value="{{ old($field, $user->$field) }}" required>
                            @if($rejectedComments->has($field) && $rejectedComments[$field]->comment)
                                <div class="form-text text-danger">{{ $rejectedComments[$field]->comment }}</div>
                            @elseif(in_array($field, $rejectedFields, true))
                                <div class="form-text text-danger">{{ __('registration_review.field_rejected') }}</div>
                            @endif
                            @error($field)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach

                    <div class="col-md-6">
                        <label for="national_id" class="form-label">{{ __('registration_review.fields.national_id') }}</label>
                        <input type="text" id="national_id" name="national_id"
                               class="form-control @error('national_id') is-invalid @enderror @if(in_array('national_id', $rejectedFields, true)) border-danger @endif"
                               value="{{ old('national_id', $user->national_id) }}" required>
                        @if($rejectedComments->has('national_id') && $rejectedComments['national_id']->comment)
                            <div class="form-text text-danger">{{ $rejectedComments['national_id']->comment }}</div>
                        @endif
                        @error('national_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="mobile_number" class="form-label">{{ __('registration_review.fields.mobile_number') }}</label>
                        <input type="text" id="mobile_number" name="mobile_number"
                               class="form-control @error('mobile_number') is-invalid @enderror @if(in_array('mobile_number', $rejectedFields, true)) border-danger @endif"
                               value="{{ old('mobile_number', $user->mobile_number) }}" required>
                        @if($rejectedComments->has('mobile_number') && $rejectedComments['mobile_number']->comment)
                            <div class="form-text text-danger">{{ $rejectedComments['mobile_number']->comment }}</div>
                        @endif
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">{{ __('registration_review.fields.email') }}</label>
                        <input type="email" id="email" name="email"
                               class="form-control @error('email') is-invalid @enderror @if(in_array('email', $rejectedFields, true)) border-danger @endif"
                               value="{{ old('email', $user->email) }}" required>
                        @if($rejectedComments->has('email') && $rejectedComments['email']->comment)
                            <div class="form-text text-danger">{{ $rejectedComments['email']->comment }}</div>
                        @endif
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="job" class="form-label">{{ __('registration_review.fields.job') }}</label>
                        <input type="text" id="job" name="job"
                               class="form-control @error('job') is-invalid @enderror @if(in_array('job', $rejectedFields, true)) border-danger @endif"
                               value="{{ old('job', $user->job) }}" required>
                        @if($rejectedComments->has('job') && $rejectedComments['job']->comment)
                            <div class="form-text text-danger">{{ $rejectedComments['job']->comment }}</div>
                        @endif
                        @error('job')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="date_of_birth" class="form-label">{{ __('registration_review.fields.date_of_birth') }}</label>
                        <input type="date" id="date_of_birth" name="date_of_birth"
                               class="form-control @error('date_of_birth') is-invalid @enderror @if(in_array('date_of_birth', $rejectedFields, true)) border-danger @endif"
                               value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}" required>
                        @if($rejectedComments->has('date_of_birth') && $rejectedComments['date_of_birth']->comment)
                            <div class="form-text text-danger">{{ $rejectedComments['date_of_birth']->comment }}</div>
                        @endif
                        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="profile_photo" class="form-label">{{ __('registration_review.fields.profile_photo') }}</label>
                        @if($user->profile_photo)
                            <div class="mb-2">
                                <img src="{{ asset('storage/'.$user->profile_photo) }}" alt="" class="rounded" style="max-height:120px;">
                            </div>
                        @endif
                        <input type="file" id="profile_photo" name="profile_photo" accept="image/*"
                               class="form-control @error('profile_photo') is-invalid @enderror @if(in_array('profile_photo', $rejectedFields, true)) border-danger @endif">
                        @if($rejectedComments->has('profile_photo') && $rejectedComments['profile_photo']->comment)
                            <div class="form-text text-danger">{{ $rejectedComments['profile_photo']->comment }}</div>
                        @endif
                        @error('profile_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">{{ __('registration_review.resubmit') }}</button>
                    <a href="{{ route('application.status') }}" class="btn btn-outline-secondary">{{ __('pages.back') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
