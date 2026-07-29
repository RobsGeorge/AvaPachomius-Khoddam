@extends('layouts.app')

@section('title', __('people_onboarding.claim_invalid'))

@section('content')
<div class="container py-5 text-center">
    <h1 class="page-title">{{ __('people_onboarding.claim_invalid') }}</h1>
    <a href="{{ route('login') }}" class="btn btn-primary mt-3">{{ __('nav.login') }}</a>
</div>
@endsection
