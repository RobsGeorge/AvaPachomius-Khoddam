@extends('layouts.app')

@section('title', __('events.admin_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-4 admin-page-header">
        <h1 class="page-title mb-0">{{ __('events.admin_title') }}</h1>
        <div class="admin-page-actions">
            <a href="{{ route('events.index', ['section' => 'admin']) }}" class="btn btn-outline-theme btn-sm">{{ __('events.back') }}</a>
            <a href="{{ route('events.admin.create') }}" class="btn btn-primary btn-sm">{{ __('events.admin_create') }}</a>
        </div>
    </div>

    @include('events.partials.admin-list', ['adminEvents' => $events, 'showAdminCreate' => false])
</div>
@endsection
