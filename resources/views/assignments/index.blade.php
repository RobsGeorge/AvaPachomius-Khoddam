@extends('layouts.app')

@section('title', __('pages.assignments'))

@section('content')
<div class="container animate-in">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="app-card card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="page-title mb-0">{{ __('pages.assignments') }}</h2>
                    @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                    <a href="{{ route('assignments.create') }}" class="btn btn-primary">{{ __('pages.add_assignment') }}</a>
                    @endif
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('pages.assignment_name') }}</th>
                                    <th>{{ __('pages.description') }}</th>
                                    <th>{{ __('pages.total_points') }}</th>
                                    <th>{{ __('pages.due_date') }}</th>
                                    <th>{{ __('pages.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment->assignment_name }}</td>
                                        <td>{{ Str::limit($assignment->assignment_description, 500) }}</td>
                                        <td>{{ $assignment->total_points }}</td>
                                        <td>{{ $assignment->due_date->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <a href="{{ route('assignments.show', $assignment) }}" class="btn btn-info btn-sm">{{ __('pages.view') }}</a>
                                            @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                                            <a href="{{ route('assignments.edit', $assignment) }}" class="btn btn-warning btn-sm">{{ __('pages.edit') }}</a>
                                            <form action="{{ route('assignments.destroy', $assignment) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(@json(__('pages.confirm_delete_assignment')))">{{ __('pages.delete') }}</button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted-theme">{{ __('pages.no_assignments') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
