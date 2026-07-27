@if(\App\Services\PlatformAccessService::isActive() && auth()->check() && (auth()->user()->is_superadmin ?? false))
    @php
        $platformChurch = \App\Services\PlatformAccessService::church();
    @endphp
    <div class="platform-access-banner bg-secondary text-white border-bottom sticky-top shadow-sm">
        <div class="container py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-start gap-2 small">
                <i class="bi bi-shield-fill-check fs-5 flex-shrink-0"></i>
                <div>
                    <strong>{{ __('workspace.platform_access_banner_title') }}</strong>
                    <span>{{ __('workspace.platform_access_banner_body', ['church' => $platformChurch?->name ?? __('pages.not_available')]) }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('superadmin.platform-access.stop') }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-light">
                    <i class="bi bi-box-arrow-left"></i> {{ __('workspace.platform_access_exit') }}
                </button>
            </form>
        </div>
    </div>
@endif
