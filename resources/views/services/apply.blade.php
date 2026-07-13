@extends('layouts.app')

@section('title', __('service.apply_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:640px;">
    <h1 class="page-title h4 mb-2">{{ __('service.apply_title') }}</h1>
    <p class="text-muted-theme mb-3">{{ $service->localizedTitle() }}</p>
    @if($form->instructions)
        <p class="mb-3">{{ $form->instructions }}</p>
    @endif
    <form method="POST" action="{{ route('services.apply.store', $service) }}" class="app-card card shadow-sm">
        @csrf
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="message">{{ __('service.application_message') }}</label>
                <textarea name="message" id="message" rows="4" class="form-control">{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('service.submit_application') }}</button>
        </div>
    </form>
</div>
@endsection
