@extends('layouts.app')

@section('title', __('admin.unverified_users'))

@section('content')
<div class="container py-4 animate-in">
    <h2 class="page-title mb-4">{{ __('admin.unverified_users') }}</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($unverifiedUsers->isEmpty())
        <p class="text-muted-theme">{{ __('admin.no_unverified_users') }}</p>
    @else
        <div class="app-card card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('admin.user_id') }}</th>
                            <th>{{ __('admin.user_name') }}</th>
                            <th>{{ __('admin.user_email') }}</th>
                            <th>{{ __('admin.user_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($unverifiedUsers as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->first_name }} {{ $user->second_name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.users.approve', $user->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        {{ __('pages.approve') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
