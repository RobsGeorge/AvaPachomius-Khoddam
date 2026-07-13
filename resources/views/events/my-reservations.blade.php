@extends('layouts.app')

@section('title', __('events.my_reservations'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="page-title mb-0">{{ __('events.my_reservations') }}</h1>
        <a href="{{ route('events.index') }}" class="btn btn-outline-theme btn-sm">{{ __('events.back') }}</a>
    </div>

    @include('events.partials.my-reservations')
</div>
@endsection
