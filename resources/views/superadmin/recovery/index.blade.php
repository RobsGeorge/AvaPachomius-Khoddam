@extends('layouts.app')

@section('title', __('auth.recovery_support_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('auth.recovery_support_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('auth.recovery_support_intro') }}</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="GET" action="{{ route('superadmin.recovery.index') }}" class="mb-4">
        <div class="input-group">
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="email / mobile / user_id">
            <button class="btn btn-primary" type="submit">{{ __('people.search') }}</button>
        </div>
    </form>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.recovery.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="user_id">user_id</label>
                        <input id="user_id" name="user_id" type="number" class="form-control" required value="{{ old('user_id') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="purpose">{{ __('auth.recovery_purpose') }}</label>
                        <select id="purpose" name="purpose" class="form-select" required>
                            <option value="rebind_mobile">{{ __('auth.recovery_purpose_mobile') }}</option>
                            <option value="rebind_email">{{ __('auth.recovery_purpose_email') }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="asserted_value">{{ __('auth.recovery_asserted_value') }}</label>
                        <input id="asserted_value" name="asserted_value" class="form-control" required value="{{ old('asserted_value') }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-warning w-100">{{ __('auth.recovery_vouch') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($q !== '' && $users->isNotEmpty())
        <div class="app-card card shadow-sm">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>{{ __('auth.email') }}</th>
                            <th>mobile</th>
                            <th>minor</th>
                            <th>safeguarding</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>{{ $user->user_id }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->mobile_number }}</td>
                                <td>{{ $user->is_minor ? 'yes' : 'no' }}</td>
                                <td>{{ $user->safeguarding_restricted ? 'yes' : 'no' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
