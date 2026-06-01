@extends('layouts.app')

@section('title', __('auth.recover_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:520px;">
    <h1 class="page-title mb-4">{{ __('auth.recover_title') }}</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="app-card card">
        <div class="card-body p-4">
            <p class="text-muted-theme mb-2">{{ __('auth.contact_reset_info') }}</p>
            <p class="text-muted-theme mb-0">{{ __('auth.contact_support') }}</p>
        </div>
    </div>
</div>
@endsection
