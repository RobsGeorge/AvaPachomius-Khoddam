@extends('layouts.app')

@section('title', __('events.title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="page-title mb-0">{{ __('events.title') }}</h1>
        @if($canAdmin && $section === 'admin')
            <a href="{{ route('events.admin.create') }}" class="btn btn-primary btn-sm">{{ __('events.admin_create') }}</a>
        @endif
    </div>

    @if($showTabs)
        <ul class="nav nav-tabs mb-4">
            @if($canBrowse)
                <li class="nav-item">
                    <a class="nav-link {{ $section === 'browse' ? 'active' : '' }}"
                       href="{{ route('events.index', ['section' => 'browse']) }}">
                        {{ __('events.section_browse') }}
                    </a>
                </li>
            @endif
            @if($canReservations)
                <li class="nav-item">
                    <a class="nav-link {{ $section === 'reservations' ? 'active' : '' }}"
                       href="{{ route('events.index', ['section' => 'reservations']) }}">
                        {{ __('events.section_reservations') }}
                    </a>
                </li>
            @endif
            @if($canAdmin)
                <li class="nav-item">
                    <a class="nav-link {{ $section === 'admin' ? 'active' : '' }}"
                       href="{{ route('events.index', ['section' => 'admin']) }}">
                        {{ __('events.section_admin') }}
                    </a>
                </li>
            @endif
        </ul>
    @endif

    @if($section === 'admin' && $canAdmin)
        @include('events.partials.admin-list', ['adminEvents' => $adminEvents, 'showAdminCreate' => false])
    @elseif($section === 'reservations' && $canReservations)
        @include('events.partials.my-reservations')
    @else
        @include('events.partials.browse')
    @endif
</div>
@endsection
