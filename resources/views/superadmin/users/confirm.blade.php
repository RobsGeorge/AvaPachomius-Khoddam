@extends('layouts.app')

@section('title', __('user_deletion.confirm_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:760px;">
    @include('superadmin.partials.header', ['title' => __('user_deletion.confirm_title')])

    <p class="text-muted-theme mb-4">{{ __('user_deletion.confirm_intro') }}</p>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">{{ $target->displayName() }}</h2>
            <dl class="row mb-0 small">
                <dt class="col-sm-3">{{ __('user_deletion.col_id') }}</dt>
                <dd class="col-sm-9">{{ $target->user_id }}</dd>
                <dt class="col-sm-3">{{ __('user_deletion.col_email') }}</dt>
                <dd class="col-sm-9">{{ $target->email }}</dd>
                <dt class="col-sm-3">{{ __('user_deletion.col_mobile') }}</dt>
                <dd class="col-sm-9">{{ $target->mobile_number ?: __('user_deletion.none') }}</dd>
                <dt class="col-sm-3">{{ __('user_deletion.col_churches') }}</dt>
                <dd class="col-sm-9">{{ $target->churches->map->preferredShortName()->filter()->unique()->implode(', ') ?: __('user_deletion.none') }}</dd>
                <dt class="col-sm-3">{{ __('user_deletion.col_services') }}</dt>
                <dd class="col-sm-9">{{ $target->userServiceRoles->map(fn ($row) => $row->service?->localizedTitle())->filter()->unique()->implode(', ') ?: __('user_deletion.none') }}</dd>
                <dt class="col-sm-3">{{ __('user_deletion.col_status') }}</dt>
                <dd class="col-sm-9">
                    @if($target->trashed())
                        <span class="badge bg-secondary">{{ __('user_deletion.status_deleted') }}</span>
                    @else
                        <span class="badge bg-success">{{ __('user_deletion.status_active') }}</span>
                    @endif
                    @if($target->is_superadmin)
                        <span class="badge bg-danger">{{ __('user_deletion.status_superadmin') }}</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>

    @if($target->trashed())
        <div class="alert alert-warning">{{ __('user_deletion.already_deleted_hint') }}</div>
    @endif

    @if(! $target->trashed())
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-2">{{ __('user_deletion.notify_section') }}</h2>
                <p class="text-muted-theme small mb-3">{{ __('user_deletion.notify_hint') }}</p>

                <form method="POST" action="{{ route('superadmin.users.soft-delete', $target->user_id) }}">
                    @csrf
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="notify_email" value="1" id="notify_email_soft"
                               @checked(old('notify_email')) @disabled(! filled($target->email))>
                        <label class="form-check-label" for="notify_email_soft">{{ __('user_deletion.notify_email') }}</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="notify_whatsapp" value="1" id="notify_whatsapp_soft"
                               @checked(old('notify_whatsapp')) @disabled(! filled($target->mobile_number))>
                        <label class="form-check-label" for="notify_whatsapp_soft">{{ __('user_deletion.notify_whatsapp') }}</label>
                    </div>

                    <h3 class="h6 mb-2">{{ __('user_deletion.soft_section') }}</h3>
                    <p class="text-muted-theme small mb-3">{{ __('user_deletion.soft_help') }}</p>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-person-dash"></i> {{ __('user_deletion.soft_action') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    <div class="app-card card shadow-sm border-danger border-opacity-50 mb-4">
        <div class="card-body">
            <h2 class="h6 text-danger mb-2">{{ __('user_deletion.hard_section') }}</h2>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                {{ __('user_deletion.hard_warning') }}
            </div>

            <form method="POST" action="{{ route('superadmin.users.hard-delete', $target->user_id) }}">
                @csrf
                @if(! $target->trashed())
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="notify_email" value="1" id="notify_email_hard"
                               @disabled(! filled($target->email))>
                        <label class="form-check-label" for="notify_email_hard">{{ __('user_deletion.notify_email') }}</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="notify_whatsapp" value="1" id="notify_whatsapp_hard"
                               @disabled(! filled($target->mobile_number))>
                        <label class="form-check-label" for="notify_whatsapp_hard">{{ __('user_deletion.notify_whatsapp') }}</label>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label" for="confirmation">{{ __('user_deletion.hard_confirm_label') }}</label>
                    <input id="confirmation" name="confirmation" type="text" class="form-control @error('confirmation') is-invalid @enderror"
                           autocomplete="off" required placeholder="{{ $target->email }}" value="{{ old('confirmation') }}">
                    @error('confirmation')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input @error('acknowledge') is-invalid @enderror" type="checkbox"
                           name="acknowledge" value="1" id="acknowledge" required>
                    <label class="form-check-label" for="acknowledge">{{ __('user_deletion.hard_acknowledge') }}</label>
                    @error('acknowledge')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash"></i> {{ __('user_deletion.hard_action') }}
                </button>
            </form>
        </div>
    </div>

    <a href="{{ route('superadmin.users.index') }}" class="btn btn-outline-secondary btn-sm">
        {{ __('user_deletion.back_to_search') }}
    </a>
</div>
@endsection
