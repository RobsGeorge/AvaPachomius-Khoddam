@extends('layouts.app')

@section('title', __('registration_review.review_title'))

@section('content')
@php
    use App\Models\RegistrationApplication;
    use App\Models\RegistrationApplicationFieldReview;
    $snapshot = $application->snapshot ?? [];
    $canReview = in_array($application->status, [
        RegistrationApplication::STATUS_PENDING_REVIEW,
        RegistrationApplication::STATUS_NEEDS_CORRECTION,
    ], true);
@endphp
<div class="container-fluid py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('registration_review.review_title') }}</h1>
            <p class="text-muted-theme mb-0">
                {{ $application->user?->displayName() }} — {{ $application->user?->email }}
            </p>
        </div>
        <a href="{{ route('admin.registration-applications.index') }}" class="btn btn-outline-secondary btn-sm">
            {{ __('pages.back') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="mb-3">
        <span class="badge bg-secondary">{{ __('registration_review.status_'.$application->status) }}</span>
        <span class="text-muted-theme small ms-2">
            {{ __('registration_review.submitted_at') }}: {{ $application->submitted_at?->format('d/m/Y H:i') }}
            · {{ __('registration_review.version') }} {{ $application->version }}
        </span>
    </div>

    @if($canReview)
        <form method="POST" action="{{ route('admin.registration-applications.show', $application) }}" id="review-form">
            @csrf
            <div class="row g-3 mb-4">
                @foreach(RegistrationApplication::REVIEWABLE_FIELDS as $fieldKey)
                    @php
                        $review = $fieldReviews[$fieldKey] ?? null;
                        $isRejected = old("fields.{$fieldKey}.status", $review?->status) === RegistrationApplicationFieldReview::STATUS_REJECTED;
                        $value = $snapshot[$fieldKey] ?? '';
                    @endphp
                    <div class="col-lg-6">
                        <div class="app-card card shadow-sm h-100 {{ $isRejected ? 'border-danger' : '' }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h2 class="h6 mb-0">{{ __('registration_review.fields.'.$fieldKey) }}</h2>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="fields[{{ $fieldKey }}][status]"
                                               id="field-{{ $fieldKey }}-accept" value="accepted"
                                               @checked(! $isRejected)>
                                        <label class="btn btn-outline-success" for="field-{{ $fieldKey }}-accept">
                                            {{ __('registration_review.accept_field') }}
                                        </label>
                                        <input type="radio" class="btn-check" name="fields[{{ $fieldKey }}][status]"
                                               id="field-{{ $fieldKey }}-reject" value="rejected"
                                               @checked($isRejected)>
                                        <label class="btn btn-outline-danger" for="field-{{ $fieldKey }}-reject">
                                            {{ __('registration_review.reject_field') }}
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="small text-muted-theme">{{ __('registration_review.field_value') }}</div>
                                    @if($fieldKey === 'profile_photo' && filled($value))
                                        <img src="{{ asset('storage/'.$value) }}" alt="" class="rounded mt-1" style="max-height:140px;">
                                    @else
                                        <div class="fw-semibold">{{ $value ?: '—' }}</div>
                                    @endif
                                </div>

                                <label class="form-label small" for="comment-{{ $fieldKey }}">{{ __('registration_review.field_comment') }}</label>
                                <textarea id="comment-{{ $fieldKey }}" name="fields[{{ $fieldKey }}][comment]" rows="2"
                                          class="form-control form-control-sm">{{ old("fields.{$fieldKey}.comment", $review?->comment) }}</textarea>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="app-card card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="course_id" class="form-label">{{ __('registration_review.assign_course') }}</label>
                            <select name="course_id" id="course_id" class="form-select" required>
                                <option value="">{{ __('pages.select_course') }}</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->course_id }}" @selected(old('course_id') == $course->course_id)>
                                        {{ $course->title }} ({{ $course->year }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="role_id" class="form-label">{{ __('registration_review.assign_role') }}</label>
                            <select name="role_id" id="role_id" class="form-select" required>
                                <option value="">{{ __('pages.select_role') }}</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->role_id }}" @selected(old('role_id') == $role->role_id)>
                                        {{ $role->role_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="hidden" name="allow_rejected_fields" value="0">
                                <input class="form-check-input" type="checkbox" name="allow_rejected_fields" value="1"
                                       id="allow_rejected_fields" @checked(old('allow_rejected_fields'))>
                                <label class="form-check-label" for="allow_rejected_fields">
                                    {{ __('registration_review.allow_rejected_fields') }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-warning"
                                formaction="{{ route('admin.registration-applications.request-corrections', $application) }}">
                            {{ __('registration_review.request_corrections') }}
                        </button>
                        <button type="submit" class="btn btn-success"
                                formaction="{{ route('admin.registration-applications.approve', $application) }}">
                            {{ __('registration_review.approve_application') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="app-card card shadow-sm border-danger">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.registration-applications.reject', $application) }}"
                      data-confirm="{{ __('registration_review.confirm_reject_application') }}"
                      onsubmit="return confirm(this.dataset.confirm);">
                    @csrf
                    <label for="overall_rejection_note" class="form-label">{{ __('registration_review.overall_rejection_note') }}</label>
                    <textarea id="overall_rejection_note" name="overall_rejection_note" rows="3" class="form-control mb-3" required>{{ old('overall_rejection_note') }}</textarea>
                    <button type="submit" class="btn btn-danger">{{ __('registration_review.reject_application') }}</button>
                </form>
            </div>
        </div>
    @else
        <div class="row g-3 mb-4">
            @foreach(RegistrationApplication::REVIEWABLE_FIELDS as $fieldKey)
                @php($value = $snapshot[$fieldKey] ?? '')
                <div class="col-lg-6">
                    <div class="app-card card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6">{{ __('registration_review.fields.'.$fieldKey) }}</h2>
                            @if($fieldKey === 'profile_photo' && filled($value))
                                <img src="{{ asset('storage/'.$value) }}" alt="" class="rounded mt-1" style="max-height:140px;">
                            @else
                                <div>{{ $value ?: '—' }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($application->status === RegistrationApplication::STATUS_REJECTED)
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    @if(filled($application->overall_rejection_note))
                        <p><strong>{{ __('registration_review.overall_rejection_note') }}:</strong> {{ $application->overall_rejection_note }}</p>
                    @endif
                    <form method="POST" action="{{ route('admin.registration-applications.restore', $application) }}"
                          data-confirm="{{ __('registration_review.confirm_restore') }}"
                          onsubmit="return confirm(this.dataset.confirm);">
                        @csrf
                        <input type="hidden" name="target_status" value="pending_review">
                        <button type="submit" class="btn btn-primary">{{ __('registration_review.restore_application') }}</button>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
