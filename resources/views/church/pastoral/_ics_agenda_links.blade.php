@if(! empty($icsAgendaLinks) && count($icsAgendaLinks))
<div class="app-card card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6 mb-1">{{ __('church_mgmt.ics_agenda_title') }}</h2>
        <p class="small text-muted-theme mb-2">{{ __('church_mgmt.ics_agenda_hint') }}</p>
        @foreach($icsAgendaLinks as $link)
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <span class="small fw-semibold">{{ $link['priest']->displayName() }}</span>
                <input type="text" class="form-control form-control-sm" style="max-width:24rem;" value="{{ $link['url'] }}" readonly onclick="this.select()">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText('{{ $link['url'] }}')">{{ __('church_mgmt.ics_copy_link') }}</button>
                <form method="POST" action="{{ route('church.ics.priest-agenda.regenerate', $link['priest']) }}" onsubmit="return confirm(@json(__('church_mgmt.ics_regenerate_confirm')))">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('church_mgmt.ics_regenerate_link') }}</button>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endif
