@extends('layouts.app')

@section('title', $person->display_name)

@section('content')
<div class="container py-4 animate-in">
    <h1 class="page-title mb-1">{{ $person->display_name }}</h1>
    <p class="text-muted-theme">{{ $person->email }} · {{ $person->mobile_number }}</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @can('viewAny', App\Models\Sacrament::class)
        <div class="mb-3">
            <a href="{{ route('people.sacraments.index', $person) }}" class="btn btn-outline-primary">
                {{ __('sacraments.title') }}
            </a>
            @can('create', App\Models\Sacrament::class)
                <a href="{{ route('people.sacraments.create', $person) }}" class="btn btn-primary">
                    {{ __('sacraments.record') }}
                </a>
            @endcan
        </div>
    @endcan

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="app-card card shadow-sm">
                <div class="card-header">{{ __('people_onboarding.placements') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse($placements as $placement)
                        <li class="list-group-item">
                            {{ $placement->service?->title_en ?: $placement->service?->title }}
                            @if($placement->course)
                                · {{ $placement->course->title }}
                            @endif
                            <span class="badge text-bg-secondary">{{ $placement->placement_mode }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted-theme">—</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card card shadow-sm mb-3">
                <div class="card-header">{{ __('people_onboarding.invitations') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse($invitations as $invitation)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $invitation->status }} · {{ $invitation->email }}</span>
                            <small>{{ $invitation->created_at }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-muted-theme">—</li>
                    @endforelse
                </ul>
            </div>
            <form method="POST" action="{{ route('people.invite', $person) }}" class="app-card card shadow-sm">
                @csrf
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="send_email" value="1" id="send_email" checked>
                        <label class="form-check-label" for="send_email">{{ __('people_onboarding.bulk_invite_email') }}</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="send_whatsapp" value="1" id="send_whatsapp">
                        <label class="form-check-label" for="send_whatsapp">{{ __('people_onboarding.bulk_invite_whatsapp') }}</label>
                    </div>
                    <button class="btn btn-primary" type="submit">{{ __('people_onboarding.invite_now') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
