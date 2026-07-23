@extends('layouts.app')

@section('title', __('service.manage_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        @if(auth()->user()->is_superadmin ?? false)
            <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-right"></i> {{ __('pages.back_to_superadmin') }}
            </a>
        @else
            <a href="{{ route('hubs.service') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-right"></i> {{ __('nav.service') }}
            </a>
        @endif
        <h1 class="page-title mb-0">
            <i class="fas fa-church me-2"></i>{{ __('service.manage_title') }}
        </h1>
    </div>

    <p class="text-muted-theme small mb-4">{{ __('service.manage_intro') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="app-card card shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="fas fa-church"></i> {{ __('service.manage_list') }}
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-compact">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('service.label') }}</th>
                                    <th>{{ __('events.status') }}</th>
                                    <th>{{ __('service.courses_count') }}</th>
                                    <th>{{ __('service.roster_title') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($services as $service)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $service->localizedTitle() }}</div>
                                            @if($service->description)
                                                <div class="text-muted-theme small text-truncate" style="max-width:220px;">{{ $service->description }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($service->status === \App\Models\ChurchService::STATUS_ACTIVE)
                                                <span class="badge bg-success">{{ __('service.status_active') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('service.status_archived') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $service->courses_count }}</td>
                                        <td>{{ $service->user_service_roles_count }}</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('admin.services.edit', $service) }}" class="btn btn-xs btn-outline-primary py-0 px-1">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="{{ app(\App\Services\RolesHubService::class)->hubUrl(null, 'service', $service) }}" class="btn btn-xs btn-outline-theme py-0 px-1" title="{{ __('rbac.section_service') }}">
                                                <i class="bi bi-shield-check"></i>
                                            </a>
                                            @if($service->status === \App\Models\ChurchService::STATUS_ACTIVE)
                                                <form method="POST" action="{{ route('admin.services.archive', $service) }}" class="d-inline"
                                                      data-confirm="{{ __('service.confirm_archive') }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1">
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted-theme py-3">{{ __('service.no_services') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="app-card card shadow-sm border-primary">
                <div class="card-header bg-primary text-white fw-semibold">
                    <i class="bi bi-plus-circle"></i> {{ __('service.create') }}
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.services.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('service.field_title') }}</label>
                            <input type="text" name="title" class="form-control form-control-sm @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" maxlength="120" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">{{ __('service.field_title_ar') }}</label>
                                <input type="text" name="title_ar" class="form-control form-control-sm" value="{{ old('title_ar') }}" maxlength="120">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">{{ __('service.field_title_en') }}</label>
                                <input type="text" name="title_en" class="form-control form-control-sm" value="{{ old('title_en') }}" maxlength="120">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('pages.description') }}</label>
                            <textarea name="description" rows="2" class="form-control form-control-sm" maxlength="2000">{{ old('description') }}</textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="clone_templates" value="1" id="clone_templates" checked>
                            <label class="form-check-label small" for="clone_templates">{{ __('service.clone_templates') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-plus-circle"></i> {{ __('service.create') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
