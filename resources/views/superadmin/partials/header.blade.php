<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-right"></i> {{ __('pages.back_to_superadmin') }}
    </a>
    <h1 class="page-title mb-0">
        @include('partials.superadmin-entry-tag', ['class' => 'me-2'])
        {{ $title }}
    </h1>
</div>

