@extends('layouts.app')

@section('title', __('people_onboarding.hub_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('people_onboarding.hub_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('people_onboarding.hub_intro') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('people.import.template') }}" class="btn btn-outline-secondary">{{ __('people_onboarding.download_template') }}</a>
            <a href="{{ route('people.import.create') }}" class="btn btn-outline-primary">{{ __('people_onboarding.import_csv') }}</a>
            <a href="{{ route('people.create') }}" class="btn btn-primary">{{ __('people_onboarding.add_person') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('people.index') }}" class="mb-3">
        <div class="input-group">
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="{{ __('people_onboarding.search_placeholder') }}">
            <button class="btn btn-primary" type="submit">{{ __('people_onboarding.search') }}</button>
        </div>
    </form>

    <div class="table-responsive app-card card shadow-sm">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('people_onboarding.col_name') }}</th>
                    <th>{{ __('people_onboarding.col_email') }}</th>
                    <th>{{ __('people_onboarding.col_mobile') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($people as $person)
                    <tr>
                        <td>{{ $person->display_name }}</td>
                        <td>{{ $person->email }}</td>
                        <td>{{ $person->mobile_number }}</td>
                        <td class="text-end">
                            <a href="{{ route('people.show', $person) }}">{{ __('people_onboarding.view_person') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted-theme py-4">{{ __('people_onboarding.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $people->links() }}</div>
</div>
@endsection
