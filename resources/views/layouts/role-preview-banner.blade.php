@if(\App\Services\RolePreviewService::isActive() && auth()->check() && (auth()->user()->is_superadmin ?? false))
    <div class="role-preview-banner role-preview-banner--overlay bg-info text-dark border-bottom border-info-subtle shadow-sm">
        <div class="container py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-start gap-2 small">
                <i class="bi bi-person-badge-fill fs-5 flex-shrink-0"></i>
                <div>
                    <strong>{{ __('pages.role_preview_banner_title') }}</strong>
                    <span>{{ __('pages.role_preview_banner_body', ['label' => \App\Services\RolePreviewService::label()]) }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('superadmin.role-preview.stop') }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-dark">
                    <i class="bi bi-box-arrow-left"></i> {{ __('pages.role_preview_exit') }}
                </button>
            </form>
        </div>
    </div>
@endif
