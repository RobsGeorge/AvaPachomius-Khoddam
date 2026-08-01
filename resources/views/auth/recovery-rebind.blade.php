@extends('layouts.app')

@section('body_class', 'auth-form-page')
@section('title', __('auth.recovery_rebind_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:460px;">
    <div class="text-center mb-4">
        <h2 class="page-title mb-1">{{ __('auth.recovery_rebind_title') }}</h2>
        <p class="text-muted-theme small">{{ __('auth.recovery_rebind_intro') }}</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="app-card card mb-3">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('recovery.rebind.start') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="identifier">{{ __('auth.identifier') }}</label>
                    <input id="identifier" name="identifier" class="form-control" required value="{{ old('identifier') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="purpose">{{ __('auth.recovery_purpose') }}</label>
                    <select id="purpose" name="purpose" class="form-select" required>
                        <option value="rebind_mobile">{{ __('auth.recovery_purpose_mobile') }}</option>
                        <option value="rebind_email">{{ __('auth.recovery_purpose_email') }}</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="asserted_value">{{ __('auth.recovery_asserted_value') }}</label>
                    <input id="asserted_value" name="asserted_value" class="form-control" required value="{{ old('asserted_value') }}">
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('auth.recovery_start') }}</button>
            </form>
        </div>
    </div>
    <p class="text-center small"><a href="{{ route('password.request') }}">{{ __('auth.recover_title') }}</a></p>
</div>
@endsection
