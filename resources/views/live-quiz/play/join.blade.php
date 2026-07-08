@extends('layouts.app')

@section('title', __('pages.live_quiz_join'))

@section('content')
<div class="container py-4 animate-in" style="max-width:520px;">
    <h1 class="page-title mb-4 text-center">{{ __('pages.live_quiz_join') }}</h1>
    <div class="app-card card"><div class="card-body p-4">
        @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <form method="POST" action="{{ route('live-quiz.play.join.submit') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">{{ __('pages.live_quiz_code') }}</label>
                <input type="text" name="join_code" class="form-control text-uppercase text-center fs-3" maxlength="6" required>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('pages.live_quiz_team_number') }}</label>
                <input type="number" name="team_number" class="form-control" min="1" max="20" placeholder="{{ __('pages.live_quiz_team_optional') }}">
            </div>
            <button class="btn btn-primary w-100">{{ __('pages.join') }}</button>
        </form>
    </div></div>
</div>
@endsection
