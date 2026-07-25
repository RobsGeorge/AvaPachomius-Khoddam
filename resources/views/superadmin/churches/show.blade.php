@extends('layouts.app')

@section('title', $church->name)

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
        <div>
            <h1 class="page-title mb-1">{{ $church->name }}</h1>
            <p class="text-muted-theme mb-0">
                <code>{{ $church->slug }}</code>
                · <a href="{{ $url }}" target="_blank" rel="noopener">{{ $host }}</a>
                · <span class="badge {{ $church->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ __('tenancy.status_'.$church->status) }}</span>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('superadmin.churches.edit', $church) }}" class="btn btn-outline-primary btn-sm">{{ __('tenancy.edit') }}</a>
            @if($church->status === 'active' && $church->slug !== config('tenancy.main_slug'))
                <form method="POST" action="{{ route('superadmin.churches.suspend', $church) }}">
                    @csrf
                    <button class="btn btn-outline-warning btn-sm" type="submit">{{ __('tenancy.suspend') }}</button>
                </form>
            @elseif($church->status !== 'active')
                <form method="POST" action="{{ route('superadmin.churches.activate', $church) }}">
                    @csrf
                    <button class="btn btn-outline-success btn-sm" type="submit">{{ __('tenancy.activate') }}</button>
                </form>
            @endif
            <a href="{{ route('superadmin.churches.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('tenancy.back') }}</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="app-card card shadow-sm h-100">
                <div class="card-header fw-semibold">{{ __('tenancy.capabilities') }}</div>
                <ul class="list-group list-group-flush">
                    @foreach($catalog as $key => $def)
                        @php $on = $church->capabilities->firstWhere('capability_key', $key)?->enabled; @endphp
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ __($def['label']) }}</span>
                            <span class="badge {{ $on ? 'bg-success' : 'bg-light text-muted' }}">{{ $on ? __('tenancy.enabled') : __('tenancy.disabled') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card card shadow-sm h-100">
                <div class="card-header fw-semibold">{{ __('tenancy.church_roles') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse($churchRoles as $role)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $role->role_name }}</span>
                            <code class="small">{{ $role->slug }}</code>
                        </li>
                    @empty
                        <li class="list-group-item text-muted-theme">{{ __('tenancy.no_roles') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="app-card card shadow-sm mt-4">
        <div class="card-header fw-semibold">{{ __('tenancy.members') }}</div>
        <div class="card-body border-bottom">
            <form method="POST" action="{{ route('superadmin.churches.members.store', $church) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label" for="email">{{ __('tenancy.member_email') }}</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="role_id">{{ __('tenancy.member_role') }}</label>
                    <select name="role_id" id="role_id" class="form-select">
                        <option value="">{{ __('tenancy.no_role') }}</option>
                        @foreach($churchRoles as $role)
                            <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">{{ __('tenancy.add_member') }}</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('tenancy.member_email') }}</th>
                        <th>{{ __('tenancy.col_status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($church->members as $membership)
                        <tr>
                            <td>{{ $membership->user?->email }}</td>
                            <td>{{ $membership->status }}</td>
                            <td class="text-end">
                                @if($membership->user)
                                    <form method="POST" action="{{ route('superadmin.churches.members.destroy', [$church, $membership->user]) }}"
                                          data-confirm="{{ __('tenancy.confirm_remove_member') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('tenancy.remove') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted-theme text-center py-3">{{ __('tenancy.no_members') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
