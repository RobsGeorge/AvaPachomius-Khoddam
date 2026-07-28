{{-- System-wide navigation overlay. Shown by public/js/kh-loader.js on internal link clicks
     and form submits; auto-hidden when the next document paints (incl. bfcache restore).
     Opt out on a link/form with the data-no-loader attribute. --}}
<div id="kh-page-loader" class="kh-page-loader" role="status" aria-live="polite" aria-hidden="true">
    <div class="kh-page-loader__box">
        <div class="kh-page-loader__disc">
            <x-orbit />
        </div>
        <span class="kh-page-loader__label">{{ __('app.loading') }}</span>
    </div>
</div>
