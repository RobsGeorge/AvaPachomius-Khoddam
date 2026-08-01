@extends('layouts.app')

@section('title', __('auth.recovery_assist_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:640px;">
    <h1 class="page-title mb-1">{{ __('auth.recovery_assist_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('auth.recovery_assist_intro') }}</p>

    @if(! $user)
        <div class="alert alert-warning">{{ __('auth.credentials_mismatch') }}</div>
    @else
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        <div class="app-card card">
            <div class="card-body">
                <p class="small text-muted-theme">{{ $person->display_name }} — {{ $user->email }} / {{ $user->mobile_number }}</p>
                <form method="POST" action="{{ route('people.recovery.store', $person) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="purpose">{{ __('auth.recovery_purpose') }}</label>
                        <select id="purpose" name="purpose" class="form-select" required>
                            <option value="rebind_mobile">{{ __('auth.recovery_purpose_mobile') }}</option>
                            <option value="rebind_email">{{ __('auth.recovery_purpose_email') }}</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="asserted_value">{{ __('auth.recovery_asserted_value') }}</label>
                        <input id="asserted_value" name="asserted_value" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning">{{ __('auth.recovery_vouch') }}</button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
