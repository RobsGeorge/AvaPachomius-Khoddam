@if(! empty($icsBookingsUrl))
<div class="app-card card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6 mb-1">{{ __('church_mgmt.ics_subscribe_title') }}</h2>
        <p class="small text-muted-theme mb-2">{{ __('church_mgmt.ics_subscribe_hint') }}</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <input type="text" class="form-control form-control-sm" style="max-width:28rem;" value="{{ $icsBookingsUrl }}" readonly onclick="this.select()">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText('{{ $icsBookingsUrl }}')">{{ __('church_mgmt.ics_copy_link') }}</button>
            <form method="POST" action="{{ route('church.ics.my-bookings.regenerate') }}" onsubmit="return confirm(@json(__('church_mgmt.ics_regenerate_confirm')))">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('church_mgmt.ics_regenerate_link') }}</button>
            </form>
        </div>
    </div>
</div>
@endif
