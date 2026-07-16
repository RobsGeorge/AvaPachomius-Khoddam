@extends('layouts.app')

@section('title', __('register.duplicate_confirm_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:720px;">
    <div class="text-center mb-4">
        <h2 class="page-title mb-1">{{ __('register.duplicate_confirm_title') }}</h2>
        <p class="text-muted-theme small">{{ __('register.duplicate_confirm_intro') }}</p>
    </div>

    <div class="alert alert-warning">
        <ul class="mb-0">
            @foreach($possibleMatches as $person)
                <li>
                    {{ $person->display_name }}
                    @if($person->date_of_birth)
                        — {{ $person->date_of_birth->format('Y-m-d') }}
                    @endif
                    @if($person->mobile_number)
                        — {{ $person->mobile_number }}
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="confirm_possible_duplicate" value="1">

                @foreach(['first_name','second_name','third_name','national_id','email','job','date_of_birth','mobile_number'] as $field)
                    <input type="hidden" name="{{ $field }}" value="{{ $input[$field] ?? '' }}">
                @endforeach

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="ack" name="ack_duplicate_review" required>
                    <label class="form-check-label" for="ack">{{ __('register.duplicate_ack') }}</label>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">{{ __('register.duplicate_continue') }}</button>
                    <a href="{{ route('register') }}" class="btn btn-outline-secondary">{{ __('register.duplicate_back') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
