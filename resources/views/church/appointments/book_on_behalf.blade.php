@extends('layouts.app')
@section('title', __('church_mgmt.book_on_behalf'))
@section('content')
<div class="container py-4" style="max-width:560px;">
    <h1 class="page-title mb-3">{{ __('church_mgmt.book_on_behalf') }}</h1>
    <p class="text-muted-theme">
        {{ $slot->starts_at?->format('Y-m-d H:i') }}
        — {{ $slot->type?->displayName() }}
        — {{ $slot->priest?->displayName() }}
    </p>
    <form method="POST" action="{{ route('church.appointments.book-on-behalf.store', $slot) }}" class="app-card card shadow-sm">
        @csrf
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="user_id">{{ __('church_mgmt.member') }}</label>
                <select name="user_id" id="user_id" class="form-select" required>
                    <option value="">{{ __('church_mgmt.select_member') }}</option>
                    @foreach($members as $member)
                        <option value="{{ $member->user_id }}" @selected((int) old('user_id') === (int) $member->user_id)>
                            {{ trim(($member->first_name ?? '').' '.($member->second_name ?? '')) ?: $member->email }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="notes">{{ __('church_mgmt.notes') }}</label>
                <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('church.appointments.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.book') }}</button>
        </div>
    </form>
</div>
@endsection
