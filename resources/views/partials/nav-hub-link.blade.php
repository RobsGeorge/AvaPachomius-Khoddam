<a class="{{ $linkClass ?? 'dropdown-item app-dropdown-link' }} {{ !empty($link['active']) ? 'active fw-semibold' : '' }}"
   href="{{ $link['url'] }}"
   @if(!empty($clickClose)) @click="navOpen = false" @endif>
    @include('partials.icon', ['icon' => $link['icon'], 'class' => 'me-2']){{ $link['label'] }}
    @if(!empty($link['superadmin_only']))
        @include('partials.superadmin-entry-tag', ['class' => 'ms-1'])
    @endif
</a>
