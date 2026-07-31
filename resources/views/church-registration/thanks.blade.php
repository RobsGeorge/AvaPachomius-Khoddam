@extends('layouts.app')

@section('title', __('church_applications.thanks_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:640px;">
    <div class="app-card card shadow-sm">
        <div class="card-body text-center py-5">
            <h1 class="page-title h3 mb-2">{{ __('church_applications.thanks_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('church_applications.thanks_body') }}</p>
        </div>
    </div>
</div>
@endsection
