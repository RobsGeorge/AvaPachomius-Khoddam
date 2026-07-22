@extends('layouts.app')

@section('title', __('people.merge_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('people.merge_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('people.merge_intro') }}</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('superadmin.people.merge.index') }}" class="mb-4">
        <div class="input-group">
            <input type="search" name="q" value="{{ $q }}" class="form-control"
                   placeholder="{{ __('people.search_placeholder') }}">
            <button class="btn btn-primary" type="submit">{{ __('people.search') }}</button>
        </div>
    </form>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">{{ __('people.merge_form_title') }}</h2>
            <form method="POST" action="{{ route('superadmin.people.merge.store') }}"
                  onsubmit="return confirm(@json(__('people.merge_confirm')));">
                @csrf
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" for="survivor_id">{{ __('people.survivor_id') }}</label>
                        <input id="survivor_id" name="survivor_id" type="number" class="form-control" required
                               value="{{ old('survivor_id') }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="duplicate_id">{{ __('people.duplicate_id') }}</label>
                        <input id="duplicate_id" name="duplicate_id" type="number" class="form-control" required
                               value="{{ old('duplicate_id') }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-danger w-100">{{ __('people.merge_action') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($q !== '')
        <div class="app-card card shadow-sm">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('people.col_id') }}</th>
                            <th>{{ __('people.col_name') }}</th>
                            <th>{{ __('people.col_normalized') }}</th>
                            <th>{{ __('people.col_email') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($people as $person)
                            <tr>
                                <td>{{ $person->person_id }}</td>
                                <td>{{ $person->display_name }}</td>
                                <td><code>{{ $person->normalized_name }}</code></td>
                                <td class="small">{{ $person->email }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted-theme text-center py-4">{{ __('people.no_results') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <h2 class="h6 text-muted-theme text-uppercase mb-3">{{ __('people.collision_groups') }}</h2>
        @forelse($duplicateGroups as $group)
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    <div class="fw-semibold mb-2">
                        <code>{{ $group['normalized_name'] }}</code>
                        <span class="badge bg-warning text-dark">{{ $group['count'] }}</span>
                    </div>
                    <ul class="mb-0 small">
                        @foreach($group['people'] as $person)
                            <li>#{{ $person->person_id }} — {{ $person->display_name }} ({{ $person->email }})</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @empty
            <p class="text-muted-theme">{{ __('people.no_collisions') }}</p>
        @endforelse
    @endif
</div>
@endsection
