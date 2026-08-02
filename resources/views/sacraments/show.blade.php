@extends('layouts.app')

@section('title', $sacrament->typeLabel())

@section('content')
<div class="container py-4 animate-in" style="max-width: 720px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ $sacrament->typeLabel() }}</h1>
            <p class="text-muted-theme mb-0">{{ $sacrament->person?->display_name }} · {{ $sacrament->formattedDate() }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('people.sacraments.index', $sacrament->person) }}" class="btn btn-outline-secondary">{{ __('sacraments.back_to_list') }}</a>
            @can('correct', $sacrament)
                <a href="{{ route('sacraments.correct', $sacrament) }}" class="btn btn-primary">{{ __('sacraments.correct') }}</a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card shadow-sm">
        <dl class="card-body row mb-0">
            <dt class="col-sm-4">{{ __('sacraments.fields.type') }}</dt>
            <dd class="col-sm-8">{{ $sacrament->typeLabel() }}</dd>
            <dt class="col-sm-4">{{ __('sacraments.fields.date') }}</dt>
            <dd class="col-sm-8">{{ $sacrament->formattedDate() }}</dd>
            <dt class="col-sm-4">{{ __('sacraments.fields.date_precision') }}</dt>
            <dd class="col-sm-8">{{ __('sacraments.precision.'.$sacrament->date_precision) }}</dd>
            <dt class="col-sm-4">{{ __('sacraments.fields.location_text') }}</dt>
            <dd class="col-sm-8">{{ $sacrament->location_text ?: '—' }}</dd>
            <dt class="col-sm-4">{{ __('sacraments.fields.recorded_at') }}</dt>
            <dd class="col-sm-8">{{ $sacrament->recorded_at }}</dd>
            @if($sacrament->corrects_sacrament_id)
                <dt class="col-sm-4">{{ __('sacraments.correct') }}</dt>
                <dd class="col-sm-8">
                    <a href="{{ route('sacraments.show', $sacrament->corrects_sacrament_id) }}">
                        {{ __('sacraments.original', ['id' => $sacrament->corrects_sacrament_id]) }}
                    </a>
                </dd>
            @endif
        </dl>
    </div>
</div>
@endsection
