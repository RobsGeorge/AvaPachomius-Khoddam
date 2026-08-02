@extends('layouts.app')

@section('title', __('sacraments.record'))

@section('content')
<div class="container py-4 animate-in" style="max-width: 720px;">
    <h1 class="page-title mb-1">{{ __('sacraments.record') }}</h1>
    <p class="text-muted-theme mb-3">{{ $person->display_name }}</p>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('people.sacraments.store', $person) }}" class="app-card card shadow-sm">
        @csrf
        @include('sacraments._form', ['sacrament' => null, 'types' => $types])
        <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('people.sacraments.index', $person) }}" class="btn btn-outline-secondary">{{ __('sacraments.back_to_list') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('sacraments.record') }}</button>
        </div>
    </form>
</div>
@endsection
