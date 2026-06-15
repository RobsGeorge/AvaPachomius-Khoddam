@isset($paginator)
    @if(method_exists($paginator, 'hasPages') && $paginator->hasPages())
        <div class="app-pagination mt-3 mb-1 d-flex justify-content-center">
            {{ $paginator->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
@endisset
