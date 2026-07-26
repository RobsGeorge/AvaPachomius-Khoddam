@extends('layouts.app')
@section('title', __('church_mgmt.confession_title'))
@section('content')
@php
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = $weekStart->copy()->addDays($i);
    }
@endphp
<div class="container py-4" style="max-width:1100px;">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('church_mgmt.confession_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('church_mgmt.confession_intro') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('church.confession.my-bookings') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.my_bookings') }}</a>
            @if($canManageAny)
                <a href="{{ route('church.confession.generate') }}" class="btn btn-outline-primary">{{ __('church_mgmt.generate_weekly') }}</a>
                <a href="{{ route('church.confession.create') }}" class="btn btn-primary">{{ __('church_mgmt.add_slot') }}</a>
            @endif
            @if($myPriest)
                <a href="{{ route('church.priests.secretaries', $myPriest) }}" class="btn btn-outline-secondary">{{ __('church_mgmt.manage_secretaries') }}</a>
            @endif
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="d-flex gap-2 align-items-center">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('church.confession.index', array_filter(['week' => $prevWeek, 'priest_id' => $priestFilter])) }}">‹</a>
            <strong>{{ $weekStart->timezone($timezone)->format('Y-m-d') }} — {{ $weekEnd->timezone($timezone)->format('Y-m-d') }}</strong>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('church.confession.index', array_filter(['week' => $nextWeek, 'priest_id' => $priestFilter])) }}">›</a>
            <span class="text-muted-theme small">({{ $timezone }})</span>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="week" value="{{ $weekStart->format('Y-m-d') }}">
            <label class="form-label mb-0 small" for="priest_id">{{ __('church_mgmt.filter_priest') }}</label>
            <select name="priest_id" id="priest_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('church_mgmt.all_priests') }}</option>
                @foreach($priests as $p)
                    <option value="{{ $p->priest_id }}" @selected((int) $priestFilter === (int) $p->priest_id)>{{ $p->displayName() }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-2 mb-4">
        @foreach($days as $day)
            @php
                $key = $day->format('Y-m-d');
                $daySlots = $grid->get($key, collect());
            @endphp
            <div class="col-12 col-md">
                <div class="app-card card shadow-sm h-100">
                    <div class="card-header py-2 small fw-semibold">{{ $day->translatedFormat('D j M') }}</div>
                    <div class="card-body p-2 d-flex flex-column gap-2">
                        @forelse($daySlots as $slot)
                            @php
                                $canManage = in_array((int) $slot->priest_id, $manageablePriestIds, true);
                                $myConfirmed = $slot->confirmedBookings->firstWhere('user_id', auth()->id());
                            @endphp
                            <div class="border rounded p-2 small @if(!$slot->isOpen()) opacity-75 @endif">
                                <div class="fw-semibold">
                                    {{ $slot->starts_at?->timezone($timezone)->format('H:i') }}–{{ $slot->ends_at?->timezone($timezone)->format('H:i') }}
                                </div>
                                <div class="text-muted-theme">{{ $slot->priest?->displayName() }}</div>
                                <div>{{ __('church_mgmt.remaining') }}: {{ $slot->remainingCapacity() }}/{{ $slot->capacity }}</div>
                                <div>
                                    <span class="badge text-bg-{{ $slot->status === 'open' ? 'success' : ($slot->status === 'closed' ? 'warning' : 'secondary') }}">
                                        {{ __('church_mgmt.status_'.$slot->status) }}
                                    </span>
                                </div>
                                @if($canManage && $slot->confirmedBookings->isNotEmpty())
                                    <div class="mt-1">
                                        @foreach($slot->confirmedBookings as $b)
                                            <div class="text-muted-theme">
                                                {{ trim(($b->user->first_name ?? '').' '.($b->user->second_name ?? '')) ?: $b->user->email }}
                                                @if($b->notes) — {{ \Illuminate\Support\Str::limit($b->notes, 40) }}@endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    @if($canManage)
                                        <a href="{{ route('church.confession.edit', $slot) }}" class="btn btn-sm btn-outline-primary">{{ __('church_mgmt.edit_slot') }}</a>
                                        @if($slot->status !== 'open')
                                            <form method="POST" action="{{ route('church.confession.status', $slot) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="open">
                                                <button class="btn btn-sm btn-outline-success" type="submit">{{ __('church_mgmt.open_slot') }}</button>
                                            </form>
                                        @endif
                                        @if($slot->status === 'open')
                                            <form method="POST" action="{{ route('church.confession.status', $slot) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="closed">
                                                <button class="btn btn-sm btn-outline-warning" type="submit">{{ __('church_mgmt.block_slot') }}</button>
                                            </form>
                                        @endif
                                        @if($canBookOnBehalf && $slot->isOpen() && $slot->remainingCapacity() > 0)
                                            <a href="{{ route('church.confession.book-on-behalf', $slot) }}" class="btn btn-sm btn-outline-secondary">{{ __('church_mgmt.book_on_behalf') }}</a>
                                        @endif
                                    @elseif($myConfirmed)
                                        <a href="{{ route('church.confession.bookings.reschedule', $myConfirmed) }}" class="btn btn-sm btn-outline-primary">{{ __('church_mgmt.reschedule') }}</a>
                                        <form method="POST" action="{{ route('church.confession.bookings.cancel', $myConfirmed) }}" onsubmit="return confirm(@json(__('church_mgmt.confirm_cancel')))">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('church_mgmt.cancel_booking') }}</button>
                                        </form>
                                    @elseif($slot->isOpen() && $slot->remainingCapacity() > 0)
                                        <form method="POST" action="{{ route('church.confession.book', $slot) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">{{ __('church_mgmt.book') }}</button>
                                        </form>
                                        @if($canBookOnBehalf)
                                            <a href="{{ route('church.confession.book-on-behalf', $slot) }}" class="btn btn-sm btn-outline-secondary">{{ __('church_mgmt.book_on_behalf') }}</a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-muted-theme small py-2">—</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($myBookings->isNotEmpty())
        <div class="app-card card shadow-sm">
            <div class="card-header">{{ __('church_mgmt.my_upcoming_bookings') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($myBookings as $booking)
                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            {{ $booking->slot?->starts_at?->timezone($timezone)->format('Y-m-d H:i') }}
                            — {{ $booking->slot?->priest?->displayName() }}
                        </div>
                        <div class="d-flex gap-1">
                            <a href="{{ route('church.confession.bookings.reschedule', $booking) }}" class="btn btn-sm btn-outline-primary">{{ __('church_mgmt.reschedule') }}</a>
                            <form method="POST" action="{{ route('church.confession.bookings.cancel', $booking) }}" onsubmit="return confirm(@json(__('church_mgmt.confirm_cancel')))">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('church_mgmt.cancel_booking') }}</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
