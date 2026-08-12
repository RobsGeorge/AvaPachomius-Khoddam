@extends('layouts.app')

@section('title', __('sacraments.title_for', ['name' => $person->display_name]))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('sacraments.title') }}</h1>
            <p class="text-muted-theme mb-0">
                {{ $person->display_name }}
                @if($person->isDeceased())
                    <span class="badge text-bg-secondary">{{ __('sacraments.deceased_badge') }}</span>
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('people.show', $person) }}" class="btn btn-outline-secondary">{{ __('sacraments.back_to_person') }}</a>
            @can('create', App\Models\Sacrament::class)
                <a href="{{ route('people.sacraments.create', $person) }}" class="btn btn-primary">{{ __('sacraments.record') }}</a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card shadow-sm">
        <ul class="list-group list-group-flush">
            @forelse($sacraments as $sacrament)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <a href="{{ route('sacraments.show', $sacrament) }}">{{ $sacrament->typeLabel() }}</a>
                        <div class="text-muted-theme small">{{ $sacrament->formattedDate() }}</div>
                        @if($sacrament->corrects_sacrament_id)
                            <div class="small">{{ __('sacraments.original', ['id' => $sacrament->corrects_sacrament_id]) }}</div>
                        @endif
                    </div>
                    <span class="text-muted-theme small">#{{ $sacrament->sacrament_id }}</span>
                </li>
            @empty
                <li class="list-group-item text-muted-theme">{{ __('sacraments.empty') }}</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
