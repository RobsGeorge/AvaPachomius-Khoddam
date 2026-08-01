@extends('layouts.app')

@section('title', $church->shownName())

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
        <div>
            <h1 class="page-title mb-1">{{ $church->shownName() }}</h1>
            <p class="text-muted-theme mb-0">
                <span>{{ $church->name }}</span>
                · <code>{{ $church->slug }}</code>
                · <a href="{{ $url }}" target="_blank" rel="noopener">{{ $host }}</a>
                · <span class="badge {{ $church->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ __('tenancy.status_'.$church->status) }}</span>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if(config('tenancy.enabled') && $church->status === 'active')
                <a href="{{ \App\Support\ChurchHost::temporarySignedRoute($church, 'superadmin.churches.view-as.start', $church) }}"
                   class="btn btn-outline-info btn-sm"
                   title="{{ __('workspace.view_as_church_hint') }}">
                    {{ __('workspace.view_as_church') }}
                </a>
                <a href="{{ \App\Support\ChurchHost::temporarySignedRoute($church, 'superadmin.churches.platform-enter.start', $church) }}"
                   class="btn btn-outline-secondary btn-sm"
                   title="{{ __('workspace.platform_enter_hint') }}">
                    {{ __('workspace.platform_enter') }}
                </a>
            @endif
            <a href="{{ route('superadmin.churches.edit', $church) }}" class="btn btn-outline-primary btn-sm">{{ __('tenancy.edit') }}</a>
            <a href="{{ route('superadmin.churches.billing', $church) }}" class="btn btn-outline-success btn-sm">{{ __('billing.billing') }}</a>
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
        <div class="col-lg-6">
            @include('course-content.partials.storage-quota-bar', [
                'storageQuota' => $storageQuota,
                'storageUsed' => $storageUsed,
                'storageRemaining' => $storageRemaining,
                'storagePercent' => $storagePercent,
            ])
        </div>
    </div>

    <div class="app-card card shadow-sm mt-4">
        <div class="card-header fw-semibold">{{ __('tenancy.members') }}</div>
        <div class="card-body border-bottom">
            <form method="POST" action="{{ route('superadmin.churches.members.store', $church) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label" for="email">{{ __('tenancy.member_email') }}</label>
                    <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="first_name">{{ __('tenancy.invite_first_name') }}</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name') }}"
                           placeholder="{{ __('tenancy.invite_first_name_hint') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="role_id">{{ __('tenancy.member_role') }}</label>
                    <select name="role_id" id="role_id" class="form-select">
                        <option value="">{{ __('tenancy.no_role') }}</option>
                        @foreach($churchRoles as $role)
                            <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('tenancy.add_or_invite_member') }}</button>
                </div>
                <div class="col-12 d-flex gap-3 flex-wrap">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="send_email" value="1" id="send_email" checked>
                        <label class="form-check-label" for="send_email">{{ __('people_onboarding.bulk_invite_email') }}</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="send_whatsapp" value="1" id="send_whatsapp">
                        <label class="form-check-label" for="send_whatsapp">{{ __('people_onboarding.bulk_invite_whatsapp') }}</label>
                    </div>
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

    @if($placementOrganization)
    <div class="app-card card shadow-sm mt-4">
        <div class="card-header fw-semibold">{{ __('workspace.break_glass_section') }}</div>
        <div class="card-body border-bottom">
            <p class="text-muted-theme small mb-3">{{ __('workspace.break_glass_help') }}</p>
            @if($activeBreakGlassGrant)
                <div class="alert alert-success py-2 mb-3">
                    {{ __('workspace.break_glass_active', ['until' => $activeBreakGlassGrant->expires_at?->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}
                </div>
            @else
                <div class="alert alert-warning py-2 mb-3">{{ __('workspace.break_glass_none_active') }}</div>
            @endif
            <form method="POST" action="{{ route('superadmin.churches.break-glass.store', $church) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-6">
                    <label class="form-label" for="break_glass_reason">{{ __('workspace.break_glass_reason') }}</label>
                    <input type="text" name="reason" id="break_glass_reason" class="form-control @error('reason') is-invalid @enderror"
                           value="{{ old('reason') }}" required minlength="5" maxlength="2000"
                           placeholder="{{ __('workspace.break_glass_reason_placeholder') }}">
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="duration_minutes">{{ __('workspace.break_glass_duration') }}</label>
                    <select name="duration_minutes" id="duration_minutes" class="form-select" required>
                        <option value="15">{{ __('workspace.break_glass_duration_15') }}</option>
                        <option value="60" selected>{{ __('workspace.break_glass_duration_60') }}</option>
                        <option value="240">{{ __('workspace.break_glass_duration_240') }}</option>
                        <option value="1440">{{ __('workspace.break_glass_duration_1440') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">{{ __('workspace.break_glass_create') }}</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('workspace.break_glass_col_staff') }}</th>
                        <th>{{ __('workspace.break_glass_col_reason') }}</th>
                        <th>{{ __('workspace.break_glass_col_expires') }}</th>
                        <th>{{ __('workspace.break_glass_col_self') }}</th>
                        <th>{{ __('workspace.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($breakGlassGrants as $grant)
                        <tr>
                            <td>{{ $grant->staff?->email }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($grant->reason, 80) }}</td>
                            <td>
                                <span class="badge {{ $grant->isActive() ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $grant->isActive() ? __('workspace.break_glass_status_active') : __('workspace.break_glass_status_expired') }}
                                </span>
                                <div class="small text-muted-theme">{{ $grant->expires_at?->format('Y-m-d H:i') }}</div>
                            </td>
                            <td>{{ $grant->self_approved ? __('workspace.break_glass_yes') : __('workspace.break_glass_no') }}</td>
                            <td class="text-end">
                                @if($grant->isActive())
                                    <form method="POST" action="{{ route('superadmin.churches.break-glass.revoke', [$church, $grant]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('workspace.break_glass_revoke') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted-theme text-center py-3">{{ __('workspace.break_glass_none_active') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
