@extends('layouts.app')

@section('title', __('course_applications.review_title'))

@section('content')
@php
    use App\Models\CourseApplication;
    use App\Models\CourseApplicationFieldReview;
    use App\Models\CourseApplicationFormField;
    $snapshot = $application->snapshot ?? [];
    $canReview = in_array($application->status, [
        CourseApplication::STATUS_PENDING_REVIEW,
        CourseApplication::STATUS_NEEDS_CORRECTION,
    ], true);
@endphp
<div class="container-fluid py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('course_applications.review_title') }}</h1>
            <p class="text-muted-theme mb-0">
                {{ $application->user?->displayName() }} — {{ $application->course?->title }}
            </p>
        </div>
        <a href="{{ route('admin.course-applications.index') }}" class="btn btn-outline-secondary btn-sm">
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
        <span class="badge bg-secondary">{{ __('course_applications.status_'.$application->status) }}</span>
        <span class="text-muted-theme small ms-2">
            {{ __('course_applications.submitted_at') }}: {{ $application->submitted_at?->format('d/m/Y H:i') }}
            · {{ __('course_applications.version') }} {{ $application->version }}
        </span>
    </div>

    @if($canReview)
        <form method="POST" action="{{ route('admin.course-applications.show', $application) }}" id="review-form">
            @csrf
            <div class="row g-3 mb-4">
                @foreach($application->reviewableFieldKeys() as $fieldKey)
                    @php
                        $field = $fieldLabels[$fieldKey] ?? null;
                        $review = $fieldReviews[$fieldKey] ?? null;
                        $isRejected = old("fields.{$fieldKey}.status", $review?->status) === CourseApplicationFieldReview::STATUS_REJECTED;
                        $value = $snapshot[$fieldKey] ?? '';
                    @endphp
                    <div class="col-lg-6">
                        <div class="app-card card shadow-sm h-100 {{ $isRejected ? 'border-danger' : '' }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h2 class="h6 mb-0">{{ $field?->label ?? $fieldKey }}</h2>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="fields[{{ $fieldKey }}][status]"
                                               id="field-{{ $fieldKey }}-accept" value="accepted"
                                               @checked(! $isRejected)>
                                        <label class="btn btn-outline-success" for="field-{{ $fieldKey }}-accept">
                                            {{ __('course_applications.accept_field') }}
                                        </label>
                                        <input type="radio" class="btn-check" name="fields[{{ $fieldKey }}][status]"
                                               id="field-{{ $fieldKey }}-reject" value="rejected"
                                               @checked($isRejected)>
                                        <label class="btn btn-outline-danger" for="field-{{ $fieldKey }}-reject">
                                            {{ __('course_applications.reject_field') }}
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="small text-muted-theme">{{ __('course_applications.field_value') }}</div>
                                    @if($field?->type === CourseApplicationFormField::TYPE_IMAGE && filled($value))
                                        <img src="{{ asset('storage/'.$value) }}" alt="" class="rounded mt-1" style="max-height:140px;">
                                    @elseif($field?->type === CourseApplicationFormField::TYPE_FILE && filled($value))
                                        <a href="{{ asset('storage/'.$value) }}" target="_blank">{{ basename($value) }}</a>
                                    @elseif(is_array($value))
                                        <div class="fw-semibold">{{ implode(', ', $value) ?: '—' }}</div>
                                    @elseif(is_bool($value))
                                        <div class="fw-semibold">{{ $value ? __('course_applications.yes') : __('course_applications.no') }}</div>
                                    @else
                                        <div class="fw-semibold">{{ $value ?: '—' }}</div>
                                    @endif
                                </div>

                                <label class="form-label small" for="comment-{{ $fieldKey }}">{{ __('course_applications.field_comment') }}</label>
                                <textarea id="comment-{{ $fieldKey }}" name="fields[{{ $fieldKey }}][comment]" rows="2"
                                          class="form-control form-control-sm">{{ old("fields.{$fieldKey}.comment", $review?->comment) }}</textarea>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="app-card card shadow-sm mb-4">
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input type="hidden" name="allow_rejected_fields" value="0">
                        <input class="form-check-input" type="checkbox" name="allow_rejected_fields" value="1"
                               id="allow_rejected_fields" @checked(old('allow_rejected_fields'))>
                        <label class="form-check-label" for="allow_rejected_fields">
                            {{ __('course_applications.allow_rejected_fields') }}
                        </label>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-warning"
                                formaction="{{ route('admin.course-applications.request-corrections', $application) }}">
                            {{ __('course_applications.request_corrections') }}
                        </button>
                        <button type="submit" class="btn btn-success"
                                formaction="{{ route('admin.course-applications.approve', $application) }}">
                            {{ __('course_applications.approve_application') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="app-card card shadow-sm border-danger">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.course-applications.reject', $application) }}"
                      data-confirm="{{ __('course_applications.confirm_reject_application') }}"
                      onsubmit="return confirm(this.dataset.confirm);">
                    @csrf
                    <label for="overall_rejection_note" class="form-label">{{ __('course_applications.overall_rejection_note') }}</label>
                    <textarea id="overall_rejection_note" name="overall_rejection_note" rows="3" class="form-control mb-3" required>{{ old('overall_rejection_note') }}</textarea>
                    <button type="submit" class="btn btn-danger">{{ __('course_applications.reject_application') }}</button>
                </form>
            </div>
        </div>
    @else
        <div class="row g-3 mb-4">
            @foreach($application->reviewableFieldKeys() as $fieldKey)
                @php
                    $field = $fieldLabels[$fieldKey] ?? null;
                    $value = $snapshot[$fieldKey] ?? '';
                @endphp
                <div class="col-lg-6">
                    <div class="app-card card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6">{{ $field?->label ?? $fieldKey }}</h2>
                            @if($field?->type === CourseApplicationFormField::TYPE_IMAGE && filled($value))
                                <img src="{{ asset('storage/'.$value) }}" alt="" class="rounded mt-1" style="max-height:140px;">
                            @elseif(is_array($value))
                                <div>{{ implode(', ', $value) ?: '—' }}</div>
                            @else
                                <div>{{ $value ?: '—' }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($application->status === CourseApplication::STATUS_REJECTED)
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body">
                    @if(filled($application->overall_rejection_note))
                        <p><strong>{{ __('course_applications.overall_rejection_note') }}:</strong> {{ $application->overall_rejection_note }}</p>
                    @endif
                    <form method="POST" action="{{ route('admin.course-applications.restore', $application) }}"
                          data-confirm="{{ __('course_applications.confirm_restore') }}"
                          onsubmit="return confirm(this.dataset.confirm);">
                        @csrf
                        <input type="hidden" name="target_status" value="pending_review">
                        <button type="submit" class="btn btn-primary">{{ __('course_applications.restore_application') }}</button>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
