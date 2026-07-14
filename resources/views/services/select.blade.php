@extends('layouts.app')

@section('title', __('service.select_title'))

@section('content')
<div class="container py-5 animate-in">
    <div class="text-center mb-5">
        <p class="text-muted-theme small mb-1">{{ __('app.institute_name') }}</p>
        <h1 class="page-title mb-2">{{ __('service.select_title') }}</h1>
        <p class="text-muted-theme mb-0 mx-auto" style="max-width: 36rem;">{{ __('service.select_intro') }}</p>
    </div>

    @if($services->isEmpty())
        <div class="app-card card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-church fs-1 text-muted-theme d-block mb-3"></i>
                <h2 class="h5">{{ __('service.no_services') }}</h2>
                <p class="text-muted-theme mb-0">{{ __('service.no_services_hint') }}</p>
            </div>
        </div>
    @else
        <div class="row g-4 justify-content-center">
            @foreach($services as $service)
                @php $isCurrent = ($currentService?->service_id ?? null) === $service->service_id; @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="app-card card h-100 shadow-sm {{ $isCurrent ? 'border-primary' : '' }}">
                        <div class="card-body d-flex flex-column">
                            <h2 class="h5 mb-2">{{ $service->localizedTitle() }}</h2>
                            @if($service->description)
                                <p class="small text-muted-theme mb-3 flex-grow-1">
                                    {{ \Illuminate\Support\Str::limit($service->description, 140) }}
                                </p>
                            @else
                                <div class="flex-grow-1"></div>
                            @endif
                            <form method="POST" action="{{ route('services.select.store') }}">
                                @csrf
                                <input type="hidden" name="service_id" value="{{ $service->service_id }}">
                                @if($intended ?? null)
                                    <input type="hidden" name="intended" value="{{ $intended }}">
                                @endif
                                <button type="submit" class="btn btn-primary w-100">
                                    @if($isCurrent)
                                        <i class="bi bi-check-circle"></i> {{ __('service.current_service') }}
                                    @else
                                        <i class="bi bi-box-arrow-in-right"></i> {{ __('service.select_title') }}
                                    @endif
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
