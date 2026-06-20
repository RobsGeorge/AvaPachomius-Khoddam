@extends('layouts.app')

@section('title', $event->exists ? __('events.admin_edit') : __('events.admin_create'))

@section('content')
<div class="container py-4 animate-in" style="max-width:800px;">
    <h1 class="page-title mb-4">{{ $event->exists ? __('events.admin_edit') : __('events.admin_create') }}</h1>
    @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <form method="POST" action="{{ $event->exists ? route('events.admin.update', $event->event_id) : route('events.admin.store') }}">
        @csrf @if($event->exists) @method('PUT') @endif
        <div class="mb-3"><label class="form-label">{{ __('events.event_title') }}</label>
            <input name="title" class="form-control" value="{{ old('title', $event->title) }}" required maxlength="100"></div>
        <div class="mb-3"><label class="form-label">{{ __('pages.description') }}</label>
            <textarea name="description" class="form-control" rows="3" required>{{ old('description', $event->description) }}</textarea></div>
        <div class="mb-3"><label class="form-label">{{ __('events.location') }}</label>
            <input name="location" class="form-control" value="{{ old('location', $event->location) }}"></div>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label">{{ __('events.starts') }}</label>
                <input type="datetime-local" name="starts_at" class="form-control" required value="{{ old('starts_at', $event->starts_at?->timezone(config('attendance.timezone'))->format('Y-m-d\TH:i')) }}"></div>
            <div class="col-md-6"><label class="form-label">{{ __('events.ends') }}</label>
                <input type="datetime-local" name="ends_at" class="form-control" required value="{{ old('ends_at', $event->ends_at?->timezone(config('attendance.timezone'))->format('Y-m-d\TH:i')) }}"></div>
        </div>
        <div class="mb-3"><label class="form-label">{{ __('events.capacity') }}</label>
            <input type="number" name="capacity" class="form-control" min="1" value="{{ old('capacity', $event->capacity) }}" required></div>
        <div class="mb-3"><label class="form-label">{{ __('events.visibility') }}</label>
            <select name="visibility" class="form-select">
                @foreach(['institution','course_enrolled','role_based'] as $v)
                    <option value="{{ $v }}" @selected(old('visibility', $event->visibility) === $v)>{{ __('events.visibility_'.$v) }}</option>
                @endforeach
            </select></div>
        <div class="mb-3"><label class="form-label">{{ __('pages.course') }} ({{ __('pages.optional') }})</label>
            <select name="course_id" class="form-select">
                <option value="">{{ __('pages.select_option') }}</option>
                @foreach($courses as $c)
                    <option value="{{ $c->course_id }}" @selected(old('course_id', $event->course_id) == $c->course_id)>{{ $c->title }}</option>
                @endforeach
            </select></div>
        <div class="mb-3"><label class="form-label">{{ __('events.eligible_roles') }}</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach($roles as $role)
                    <label class="form-check-label border rounded px-2 py-1">
                        <input type="checkbox" name="eligible_roles[]" value="{{ $role }}" @checked(in_array($role, old('eligible_roles', $event->eligible_roles ?? [])))> {{ $role }}
                    </label>
                @endforeach
            </div></div>
        <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
    </form>
</div>
@endsection
