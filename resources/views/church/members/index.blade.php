@extends('layouts.app')
@section('title', __('tenancy.members'))
@section('content')
<div class="container py-4" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('tenancy.members') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('tenancy.members_intro') }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('tenancy.add_or_invite_member') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('church.members.store') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label" for="email">{{ __('tenancy.member_email') }}</label>
                    <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="first_name">{{ __('tenancy.invite_first_name') }}</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name') }}"
                           placeholder="{{ __('tenancy.invite_first_name_hint') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="second_name">{{ __('tenancy.invite_second_name') }}</label>
                    <input type="text" name="second_name" id="second_name" class="form-control" value="{{ old('second_name') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mobile_number">{{ __('tenancy.invite_mobile') }}</label>
                    <input type="text" name="mobile_number" id="mobile_number" class="form-control" value="{{ old('mobile_number') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="role_id">{{ __('tenancy.member_role') }}</label>
                    <select name="role_id" id="role_id" class="form-select">
                        <option value="">{{ __('tenancy.no_role') }}</option>
                        @foreach($churchRoles as $role)
                            <option value="{{ $role->role_id }}" @selected(old('role_id') == $role->role_id)>{{ $role->role_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-3 flex-wrap">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="send_email" value="1" id="send_email" @checked(old('send_email', true))>
                        <label class="form-check-label" for="send_email">{{ __('people_onboarding.bulk_invite_email') }}</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="send_whatsapp" value="1" id="send_whatsapp" @checked(old('send_whatsapp'))>
                        <label class="form-check-label" for="send_whatsapp">{{ __('people_onboarding.bulk_invite_whatsapp') }}</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">{{ __('tenancy.add_or_invite_member') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="app-card card shadow-sm">
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
                    @forelse($members as $membership)
                        <tr>
                            <td>{{ $membership->user?->email }}</td>
                            <td>{{ $membership->status }}</td>
                            <td class="text-end">
                                @if($membership->user)
                                    <form method="POST" action="{{ route('church.members.destroy', $membership->user) }}"
                                          data-confirm="{{ __('tenancy.confirm_remove_member') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('tenancy.remove') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted-theme py-4">{{ __('tenancy.no_members') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
