@if ($paginator->hasPages())
    <nav class="app-pagination mt-3 mb-1 d-flex justify-content-center" aria-label="Pagination">
        {{ $paginator->withQueryString()->links() }}
    </nav>
@endif
