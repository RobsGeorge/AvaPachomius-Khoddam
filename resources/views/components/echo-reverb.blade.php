@props(['channel'])

@php
    $reverbKey = config('broadcasting.connections.reverb.key') ?? env('REVERB_APP_KEY');
    $reverbHost = config('broadcasting.connections.reverb.options.host') ?? env('REVERB_HOST', 'localhost');
    $reverbPort = config('broadcasting.connections.reverb.options.port') ?? env('REVERB_PORT', 8080);
    $reverbScheme = config('broadcasting.connections.reverb.options.scheme') ?? env('REVERB_SCHEME', 'http');
    $useTls = $reverbScheme === 'https';
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: @json($reverbKey),
    wsHost: @json($reverbHost),
    wsPort: {{ (int) $reverbPort }},
    wssPort: {{ (int) $reverbPort }},
    forceTLS: {{ $useTls ? 'true' : 'false' }},
    enabledTransports: ['ws', 'wss'],
    authEndpoint: @json(url('/broadcasting/auth')),
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
    },
});

@if(!empty($channel))
window.Echo.channel(@json($channel))
    .listen('.session.updated', (payload) => {
        window.dispatchEvent(new CustomEvent('live-session-updated', { detail: payload }));
    });
@endif
</script>
@endpush
