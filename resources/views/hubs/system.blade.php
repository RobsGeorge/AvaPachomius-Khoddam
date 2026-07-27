@extends('layouts.app')

@section('title', __('nav.system_settings'))

@section('content')
@php
    use App\Support\NavigationHub;
    $sections = NavigationHub::groupedSections($links, NavigationHub::systemSectionDefinitions());
@endphp
<div class="container py-4 animate-in hub-page" style="max-width:920px;">
    <h1 class="page-title mb-2">{{ __('nav.system_settings') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('nav.system_settings_desc') }}</p>

    @if(empty($sections))
        <div class="app-tile text-center text-muted-theme py-5">
            {{ __('nav.hub_empty') }}
        </div>
    @else
        @include('partials.hub-link-sections', ['sections' => $sections])
    @endif
</div>
@endsection
