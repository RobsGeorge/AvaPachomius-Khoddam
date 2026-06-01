@extends('layouts.app')

@section('title', __('pages.add_assignment'))

@section('content')
<div class="container animate-in">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="app-card card">
                <div class="card-header">
                    <h2 class="page-title mb-0">{{ __('pages.add_assignment') }}</h2>
                </div>

                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form action="{{ route('assignments.store') }}" method="POST">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="assignment_name">{{ __('pages.assignment_name') }}</label>
                            <input type="text" class="form-control @error('assignment_name') is-invalid @enderror"
                                   id="assignment_name" name="assignment_name" value="{{ old('assignment_name') }}" required>
                            @error('assignment_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="assignment_description">{{ __('pages.assignment_description') }}</label>
                            <textarea class="form-control @error('assignment_description') is-invalid @enderror"
                                      id="assignment_description" name="assignment_description" rows="3" required>{{ old('assignment_description') }}</textarea>
                            @error('assignment_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="total_points">{{ __('pages.total_points') }}</label>
                            <input type="number" class="form-control @error('total_points') is-invalid @enderror"
                                   id="total_points" name="total_points" value="{{ old('total_points') }}" min="1" required>
                            @error('total_points')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="due_date">{{ __('pages.due_date') }}</label>
                            <input type="datetime-local" class="form-control @error('due_date') is-invalid @enderror"
                                   id="due_date" name="due_date" value="{{ old('due_date') }}" required>
                            @error('due_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="instructions">{{ __('pages.instructions') }}</label>
                            <textarea class="form-control @error('instructions') is-invalid @enderror"
                                      id="instructions" name="instructions" rows="3">{{ old('instructions') }}</textarea>
                            @error('instructions')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="resources">{{ __('pages.resources') }}</label>
                            <textarea class="form-control @error('resources') is-invalid @enderror"
                                      id="resources" name="resources" rows="3">{{ old('resources') }}</textarea>
                            @error('resources')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ __('pages.add_assignment_btn') }}</button>
                            <a href="{{ route('assignments.index') }}" class="btn btn-outline-theme">{{ __('pages.cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
