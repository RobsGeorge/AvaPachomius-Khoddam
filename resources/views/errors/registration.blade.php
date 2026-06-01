@extends('layouts.app')

@section('title', __('pages.registration_error'))

@section('content')
<div class="container mt-5 text-center animate-in">
    <h2 class="page-title text-danger">{{ __('pages.registration_error_title') }}</h2>
    <p class="text-muted-theme">{{ $message }}</p>
    @if(config('app.debug'))
        <small class="text-muted-theme">{{ __('pages.error_details') }} {{ $details }}</small>
    @endif
    <a href="{{ route('register') }}" class="btn btn-primary mt-3">{{ __('pages.back_to_register') }}</a>
</div>
@endsection
