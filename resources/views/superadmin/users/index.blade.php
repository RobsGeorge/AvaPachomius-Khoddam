@extends('layouts.app')

@section('title', __('user_deletion.title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:1100px;">
    @include('superadmin.partials.header', ['title' => __('user_deletion.title')])

    <p class="text-muted-theme mb-4">{{ __('user_deletion.intro') }}</p>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('superadmin.users.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="name">{{ __('user_deletion.search_name') }}</label>
                    <input id="name" type="search" name="name" value="{{ $name }}"
                           class="form-control"
                           placeholder="{{ __('user_deletion.search_name_placeholder') }}"
                           autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="church_id">{{ __('user_deletion.search_church') }}</label>
                    <select id="church_id" name="church_id" class="form-select">
                        <option value="">{{ __('user_deletion.all_churches') }}</option>
                        @foreach($churches as $church)
                            <option value="{{ $church->church_id }}" @selected((int) $churchId === (int) $church->church_id)>
                                {{ $church->preferredShortName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="service_id">{{ __('user_deletion.search_service') }}</label>
                    <select id="service_id" name="service_id" class="form-select">
                        <option value="">{{ __('user_deletion.all_services') }}</option>
                        @foreach($services as $service)
                            <option value="{{ $service->service_id }}" @selected((int) $serviceId === (int) $service->service_id)>
                                {{ $service->localizedTitle() }}
                                @if($service->church)
                                    — {{ $service->church->preferredShortName() }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> {{ __('user_deletion.search') }}
                    </button>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="include_deleted" value="1"
                               id="include_deleted" @checked($includeDeleted)>
                        <label class="form-check-label" for="include_deleted">
                            {{ __('user_deletion.include_deleted') }}
                        </label>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(! $hasFilters)
        <div class="alert alert-info mb-0">{{ __('user_deletion.search_hint') }}</div>
    @else
        <div class="app-card card shadow-sm">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('user_deletion.col_id') }}</th>
                            <th>{{ __('user_deletion.col_name') }}</th>
                            <th>{{ __('user_deletion.col_email') }}</th>
                            <th>{{ __('user_deletion.col_mobile') }}</th>
                            <th>{{ __('user_deletion.col_churches') }}</th>
                            <th>{{ __('user_deletion.col_services') }}</th>
                            <th>{{ __('user_deletion.col_status') }}</th>
                            <th>{{ __('user_deletion.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td class="text-nowrap">{{ $user->user_id }}</td>
                                <td class="fw-semibold">{{ $user->displayName() }}</td>
                                <td class="small">{{ $user->email }}</td>
                                <td class="small">{{ $user->mobile_number ?: __('user_deletion.none') }}</td>
                                <td class="small">
                                    {{ $user->churches->map->preferredShortName()->filter()->unique()->implode(', ') ?: __('user_deletion.none') }}
                                </td>
                                <td class="small">
                                    {{ $user->userServiceRoles->map(fn ($row) => $row->service?->localizedTitle())->filter()->unique()->implode(', ') ?: __('user_deletion.none') }}
                                </td>
                                <td class="text-nowrap">
                                    @if($user->trashed())
                                        <span class="badge bg-secondary">{{ __('user_deletion.status_deleted') }}</span>
                                    @else
                                        <span class="badge bg-success">{{ __('user_deletion.status_active') }}</span>
                                    @endif
                                    @if($user->is_superadmin)
                                        <span class="badge bg-danger">{{ __('user_deletion.status_superadmin') }}</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <a href="{{ route('superadmin.users.confirm', $user->user_id) }}"
                                       class="btn btn-sm btn-outline-danger">
                                        {{ __('user_deletion.open_delete') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-muted-theme text-center py-4">{{ __('user_deletion.no_results') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users instanceof \Illuminate\Contracts\Pagination\Paginator && $users->hasPages())
                <div class="card-body border-top">{{ $users->links() }}</div>
            @endif
        </div>
    @endif
</div>
@endsection
