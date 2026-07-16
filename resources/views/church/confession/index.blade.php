@extends('layouts.app')
@section('title', __('church_mgmt.confession_title'))
@section('content')
<div class="container py-4" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('church_mgmt.confession_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('church_mgmt.confession_intro') }}</p>
        </div>
        @if($myPriest)
            <a href="{{ route('church.confession.create') }}" class="btn btn-primary">{{ __('church_mgmt.add_slot') }}</a>
        @endif
    </div>
    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('church_mgmt.starts_at') }}</th>
                        <th>{{ __('church_mgmt.ends_at') }}</th>
                        <th>{{ __('church_mgmt.priests_title') }}</th>
                        <th>{{ __('church_mgmt.location') }}</th>
                        <th>{{ __('church_mgmt.remaining') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($slots as $slot)
                        <tr>
                            <td>{{ $slot->starts_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td>{{ $slot->ends_at?->timezone(config('app.timezone'))->format('H:i') }}</td>
                            <td>{{ $slot->priest?->displayName() }}</td>
                            <td>{{ $slot->location ?: '—' }}</td>
                            <td>{{ $slot->remainingCapacity() }} / {{ $slot->capacity }}</td>
                            <td class="text-end">
                                @if($myPriest && (int) $slot->priest_id === (int) $myPriest->priest_id)
                                    <a href="{{ route('church.confession.edit', $slot) }}" class="btn btn-sm btn-outline-primary">{{ __('church_mgmt.edit_slot') }}</a>
                                @elseif($slot->isOpen() && $slot->remainingCapacity() > 0)
                                    <form method="POST" action="{{ route('church.confession.book', $slot) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary">{{ __('church_mgmt.book') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted-theme py-4">{{ __('church_mgmt.no_slots') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $slots->links() }}</div>
</div>
@endsection
