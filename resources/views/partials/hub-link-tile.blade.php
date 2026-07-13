<div class="col-sm-6">
    <a href="{{ $link['url'] }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none {{ !empty($link['active']) ? 'hub-tile-active' : '' }} {{ !empty($link['superadmin_only']) ? 'hub-tile-superadmin' : '' }}">
        <h3 class="h5 mb-1">
            @if(!empty($link['superadmin_only']))
                @include('partials.superadmin-entry-tag', ['class' => 'me-1'])
            @endif
            <i class="bi {{ $link['icon'] }}"></i>
            {{ $link['label'] }}
        </h3>
        @if(!empty($link['description']))
            <p class="text-muted-theme small mb-0">{{ $link['description'] }}</p>
        @endif
    </a>
</div>
