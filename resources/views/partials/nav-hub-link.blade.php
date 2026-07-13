<a class="{{ $linkClass ?? 'dropdown-item app-dropdown-link' }} {{ !empty($link['active']) ? 'active fw-semibold' : '' }}"
   href="{{ $link['url'] }}"
   @if(!empty($clickClose)) @click="navOpen = false" @endif>
    <i class="bi {{ $link['icon'] }} me-2"></i>{{ $link['label'] }}
    @if(!empty($link['superadmin_only']))
        @include('partials.superadmin-entry-tag', ['class' => 'ms-1'])
    @endif
</a>
