@if(\App\Services\ImpersonationService::isActive() && auth()->check())
    @php
        $viewingAs = auth()->user();
        $roleSummary = implode(', ', \App\Services\ImpersonationService::roleSummary($viewingAs));
    @endphp
    <div class="impersonation-banner bg-warning text-dark border-bottom border-warning-subtle sticky-top shadow-sm">
        <div class="container py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-start gap-2 small">
                <i class="bi bi-eye-fill fs-5 flex-shrink-0"></i>
                <div>
                    <strong>{{ __('pages.impersonate_banner_title') }}</strong>
                    <span>{{ __('pages.impersonate_banner_body', [
                        'name' => $viewingAs->displayName(),
                        'roles' => $roleSummary !== '' ? $roleSummary : __('pages.no_roles_yet'),
                    ]) }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('superadmin.impersonate.stop') }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-dark">
                    <i class="bi bi-box-arrow-left"></i> {{ __('pages.impersonate_exit') }}
                </button>
            </form>
        </div>
    </div>
@endif
