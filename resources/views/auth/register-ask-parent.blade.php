@extends('layouts.app')

@section('content')
<div class="container py-5" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="mx-auto" style="max-width: 32rem;">
        <h1 class="h3 mb-3">{{ __('maturity.ask_parent_title') }}</h1>
        <p class="text-muted mb-4">{{ __('maturity.ask_parent_body') }}</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('register') }}" class="btn btn-outline-secondary">{{ __('maturity.ask_parent_back') }}</a>
            <a href="{{ route('login') }}" class="btn btn-primary">{{ __('maturity.ask_parent_login') }}</a>
        </div>
    </div>
</div>
@endsection
