@extends('layouts.app')

@section('title', __('people_onboarding.import_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width: 640px;">
    <h1 class="page-title mb-2">{{ __('people_onboarding.import_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('people_onboarding.import_intro') }}</p>

    <p>
        <a href="{{ route('people.import.template') }}">{{ __('people_onboarding.download_template') }}</a>
    </p>

    <form method="POST" action="{{ route('people.import.store') }}" enctype="multipart/form-data" class="app-card card shadow-sm">
        @csrf
        <div class="card-body">
            <label class="form-label">{{ __('people_onboarding.import_upload') }}</label>
            <input type="file" name="file" class="form-control mb-3" accept=".csv,text/csv" required>
            <button type="submit" class="btn btn-primary">{{ __('people_onboarding.import_upload') }}</button>
        </div>
    </form>
</div>
@endsection
