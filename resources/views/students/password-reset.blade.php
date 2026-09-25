@extends('layouts.app')

@section('title', __('students.password_reset_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    @if($isSuperadminPanel)
        @include('superadmin.partials.header', ['title' => __('students.password_reset_title')])
    @else
        <div class="mb-4">
            <h1 class="page-title mb-1">{{ __('students.password_reset_title') }}</h1>
        </div>
    @endif
    <p class="text-muted-theme mb-4">{{ __('students.password_reset_intro') }}</p>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route($indexRoute) }}" class="row g-3 align-items-end">
                @if($courses->isNotEmpty())
                    <div class="col-md-4">
                        <label class="form-label" for="course">{{ __('pages.course') }}</label>
                        <select name="course" id="course" class="form-select">
                            <option value="">{{ __('students.password_reset_all_courses') }}</option>
                            @foreach($courses as $c)
                                <option value="{{ $c->course_id }}" @selected((string) $courseId === (string) $c->course_id)>
                                    {{ $c->localizedTitle() }}@if($c->year) ({{ $c->year }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="{{ $courses->isNotEmpty() ? 'col-md-6' : 'col-md-10' }}">
                    <label class="form-label" for="q">{{ __('students.password_reset_search_label') }}</label>
                    <input type="search" name="q" id="q" value="{{ $q }}" class="form-control"
                           placeholder="{{ __('students.password_reset_search_placeholder') }}"
                           autocomplete="off">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> {{ __('people.search') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(! $hasQuery)
        <div class="alert alert-info mb-0">{{ __('students.password_reset_search_hint') }}</div>
    @elseif($students->isEmpty())
        <div class="app-tile text-center text-muted-theme py-5">{{ __('students.password_reset_no_matches') }}</div>
    @else
        <div class="app-card card shadow-sm">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('pages.user') }}</th>
                            <th>{{ __('pages.email') }}</th>
                            <th>{{ __('pages.phone') }}</th>
                            <th>{{ __('pages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $student->displayName() }}</div>
                                    <div class="small text-muted-theme">#{{ $student->user_id }}</div>
                                </td>
                                <td>{{ $student->email ?: '—' }}</td>
                                <td>{{ $student->formattedMobile() ?: '—' }}</td>
                                <td>
                                    @if($student->email)
                                        <form method="POST" action="{{ route($storeRoute) }}"
                                              data-confirm="{{ __('students.password_reset_confirm', ['name' => $student->displayName()]) }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $student->user_id }}">
                                            <input type="hidden" name="q" value="{{ $q }}">
                                            @if($courseId)
                                                <input type="hidden" name="course" value="{{ $courseId }}">
                                            @endif
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <i class="bi bi-envelope"></i> {{ __('students.password_reset_send') }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted-theme small">{{ __('students.password_reset_no_email') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
