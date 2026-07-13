@extends('layouts.app')

@section('title', __('pages.assignments_dashboard'))

@section('content')
<div class="container animate-in">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="app-card card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="page-title mb-0">{{ __('pages.assignments_dashboard') }}</h2>
                    <a href="{{ route('assignments.create') }}" class="btn btn-primary">{{ __('pages.add_assignment') }}</a>
                </div>

                <div class="card-body">
                    @include('assignments.partials.management')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
