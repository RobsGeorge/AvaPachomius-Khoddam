@isset($paginator)
    @if(method_exists($paginator, 'hasPages') && $paginator->hasPages())
        {{ $paginator->withQueryString()->links('pagination::bootstrap-5') }}
    @endif
@endisset
