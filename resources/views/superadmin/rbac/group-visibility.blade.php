@extends('layouts.app')

@section('title', __('rbac.group_visibility'))

@section('content')
<div class="container py-4">
    @include('superadmin.partials.header', ['title' => __('rbac.group_visibility')])

    <form method="POST" action="{{ route('superadmin.group-visibility.update') }}">
        @csrf
        <div class="app-card card shadow-sm">
            <div class="card-body">
                @foreach($groups->whereIn('scope', ['course', 'both']) as $group)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="visible_groups[]"
                               value="{{ $group->permission_group_id }}" id="g-{{ $group->permission_group_id }}"
                               @checked($group->isVisibleToCourseAdmins())>
                        <label class="form-check-label" for="g-{{ $group->permission_group_id }}">
                            {{ $group->label() }} <small class="text-muted-theme">({{ $group->group_key }})</small>
                        </label>
                    </div>
                @endforeach
                <button type="submit" class="btn btn-primary mt-3">{{ __('rbac.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
