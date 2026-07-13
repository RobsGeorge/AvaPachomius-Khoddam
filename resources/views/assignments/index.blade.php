@extends('layouts.app')

@section('title', __('pages.assignments'))

@section('content')
<div class="container animate-in">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="app-card card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2 class="page-title mb-0">{{ __('pages.assignments') }}</h2>
                    @if($canManage)
                        <a href="{{ route('assignments.create') }}" class="btn btn-primary">{{ __('pages.add_assignment') }}</a>
                    @endif
                </div>

                <div class="card-body">
                    @if($canManage)
                        <ul class="nav nav-tabs mb-4">
                            <li class="nav-item">
                                <a class="nav-link {{ $section === 'list' ? 'active' : '' }}"
                                   href="{{ route('assignments.index', ['section' => 'list']) }}">
                                    {{ __('pages.assignments_section_list') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $section === 'manage' ? 'active' : '' }}"
                                   href="{{ route('assignments.index', ['section' => 'manage']) }}">
                                    {{ __('pages.assignments_section_manage') }}
                                </a>
                            </li>
                        </ul>
                    @endif

                    @if($section === 'manage' && $canManage)
                        @include('assignments.partials.management')
                    @else
                        @include('assignments.partials.list')
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
