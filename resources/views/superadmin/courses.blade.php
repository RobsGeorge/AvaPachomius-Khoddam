@extends('layouts.app')

@section('title', __('pages.manage_courses'))

@section('content')
<div class="container py-4 animate-in">
    @include('superadmin.partials.header', ['title' => __('pages.manage_courses')])

    @if($courses->isEmpty())
        <div class="alert alert-warning d-flex gap-3 align-items-start mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
            <div>
                <strong>{{ __('pages.create_first_course') }}</strong>
                <p class="mb-1 mt-1">{{ __('pages.create_first_course_hint') }}</p>
                <p class="mb-0 small text-muted-theme">{{ __('pages.setup_order_hint') }}</p>
            </div>
        </div>
    @else
        <p class="text-muted-theme small mb-4">{{ __('pages.setup_order_hint') }}</p>
    @endif

    <div class="app-card card shadow-sm border-primary">
        <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-journal-bookmark-fill"></i> {{ __('pages.manage_courses') }}
        </div>
        <div class="card-body p-0">
            <div class="table-responsive table-responsive-compact">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.course_title') }}</th>
                            <th>{{ __('pages.year') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courses as $course)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $course->title }}</div>
                                    <div class="text-muted-theme small text-truncate" style="max-width:240px;" title="{{ $course->description }}">
                                        {{ $course->description }}
                                    </div>
                                </td>
                                <td>{{ $course->year }}</td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('superadmin.courses.destroy', $course->course_id) }}"
                                          data-confirm="{{ __('pages.confirm_delete_course') }}"
                                          onsubmit="return confirm(this.dataset.confirm)">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted-theme py-3">
                                    {{ __('pages.no_courses_yet') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <form method="POST" action="{{ route('superadmin.courses.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">{{ __('pages.course_title') }}</label>
                        <input type="text" name="title" class="form-control form-control-sm @error('title') is-invalid @enderror"
                               value="{{ old('title') }}" maxlength="30"
                               placeholder="{{ __('pages.course_title_placeholder') }}" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">{{ __('pages.year') }}</label>
                        <input type="number" name="year" class="form-control form-control-sm @error('year') is-invalid @enderror"
                               value="{{ old('year', date('Y')) }}" min="2000" max="2100" required>
                        @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">{{ __('pages.description') }}</label>
                        <textarea name="description" rows="2" class="form-control form-control-sm @error('description') is-invalid @enderror"
                                  maxlength="255" placeholder="{{ __('pages.course_description_placeholder') }}" required>{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">{{ __('pages.default_session_start_time') }}</label>
                        <input type="time" name="default_session_start_time"
                               class="form-control form-control-sm @error('default_session_start_time') is-invalid @enderror"
                               value="{{ old('default_session_start_time', '09:00') }}" required>
                        <div class="form-text">{{ __('pages.course_default_session_start_time_hint') }}</div>
                        @error('default_session_start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 btn-sm">
                            <i class="bi bi-plus-circle"></i> {{ __('pages.create_course') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
