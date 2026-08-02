@extends('layouts.app')

@section('title', __('profile_photos.report_title'))

@section('content')
@php
    $gate = app(\App\Services\ProfilePhotoGateService::class);
    $photoAdmin = app(\App\Services\ProfilePhotoAdminService::class);
@endphp
<div class="container-fluid py-4 animate-in student-data-hub">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('profile_photos.report_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('profile_photos.report_intro') }}</p>
    </div>

<div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('profile_photos.save_settings') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.profile-photos.settings') }}" class="row g-3 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-md-3">
                    <label for="profile_photo_grace_days" class="form-label">{{ __('profile_photos.grace_days') }}</label>
                    <input type="number" min="1" max="90" class="form-control" id="profile_photo_grace_days"
                           name="profile_photo_grace_days" value="{{ old('profile_photo_grace_days', $settings->profile_photo_grace_days) }}" required>
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input type="hidden" name="profile_photo_gate_enabled" value="0">
                        <input class="form-check-input" type="checkbox" name="profile_photo_gate_enabled" value="1" id="profile_photo_gate_enabled"
                               @checked(old('profile_photo_gate_enabled', $settings->profile_photo_gate_enabled))>
                        <label class="form-check-label" for="profile_photo_gate_enabled">{{ __('profile_photos.gate_enabled') }}</label>
                    </div>
                    <p class="small text-muted-theme mb-0 mt-1">
                        @if($settings->profile_photo_gate_enabled)
                            {{ __('profile_photos.gate_status_on', ['days' => $settings->profile_photo_grace_days]) }}
                        @else
                            {{ __('profile_photos.gate_status_off') }}
                        @endif
                    </p>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">{{ __('profile_photos.save_settings') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 admin-filter-bar">
        <a href="{{ route('admin.profile-photos.index') }}" class="btn btn-sm {{ $filter ? 'btn-outline-secondary' : 'btn-primary' }}">
            {{ __('profile_photos.filter_all') }} ({{ array_sum($counts) }})
        </a>
        @foreach(['not_started', 'in_grace', 'overdue', 'pending_review', 'approved', 'rejected'] as $statusKey)
            <a href="{{ route('admin.profile-photos.index', ['filter' => $statusKey]) }}"
               class="btn btn-sm {{ $filter === $statusKey ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('profile_photos.status_'.$statusKey) }} ({{ $counts[$statusKey] ?? 0 }})
            </a>
        @endforeach
    </div>

    <div class="table-responsive d-none d-lg-block admin-table-desktop app-card card shadow-sm">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('profile_photos.student') }}</th>
                    <th>{{ __('profile_photos.status') }}</th>
                    <th>{{ __('profile_photos.grace_started') }}</th>
                    <th>{{ __('profile_photos.deadline') }}</th>
                    <th>{{ __('profile_photos.uploaded_at') }}</th>
                    <th>{{ __('profile_photos.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                    @php
                        $status = $gate->complianceStatus($student);
                        $deadline = $gate->complianceDeadline($student);
                        $graceStarted = $gate->safeDate($student, 'profile_photo_grace_started_at');
                        $uploadedAt = $gate->safeDate($student, 'profile_photo_uploaded_at');
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @include('admin.profile-photos.partials.photo-trigger', ['student' => $student, 'size' => 40])
                                <div>
                                    <strong>{{ $student->displayName() }}</strong>
                                    <div class="small text-muted-theme">{{ $student->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">{{ __('profile_photos.status_'.$status) }}</span>
                            @if($status === 'pending_review')
                                @php $wait = $photoAdmin->pendingWaitLabel($student); @endphp
                                @if($wait)
                                    <div class="small text-muted-theme mt-1">{{ $wait }}</div>
                                @endif
                            @endif
                        </td>
                        <td>{{ $graceStarted?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $deadline?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $uploadedAt?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>
                            @include('admin.profile-photos.partials.review-actions', [
                                'student' => $student,
                                'compact' => true,
                            ])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted-theme py-4">{{ __('profile_photos.no_students') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-lg-none admin-data-cards student-data-hub">
        @forelse($students as $student)
            @php
                $status = $gate->complianceStatus($student);
                $deadline = $gate->complianceDeadline($student);
                $graceStarted = $gate->safeDate($student, 'profile_photo_grace_started_at');
                $uploadedAt = $gate->safeDate($student, 'profile_photo_uploaded_at');
            @endphp
            <article class="data-card app-card card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        @include('admin.profile-photos.partials.photo-trigger', ['student' => $student, 'size' => 48])
                        <div>
                            <div class="data-card-title mb-0">{{ $student->displayName() }}</div>
                            <div class="small text-muted-theme">{{ $student->email }}</div>
                        </div>
                    </div>
                    <dl class="data-meta-list mb-3">
                        <div class="data-meta-row">
                            <dt>{{ __('profile_photos.status') }}</dt>
                            <dd>
                                <span class="badge bg-secondary">{{ __('profile_photos.status_'.$status) }}</span>
                                @if($status === 'pending_review')
                                    @php $wait = $photoAdmin->pendingWaitLabel($student); @endphp
                                    @if($wait)
                                        <div class="small text-muted-theme mt-1">{{ $wait }}</div>
                                    @endif
                                @endif
                            </dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('profile_photos.grace_started') }}</dt>
                            <dd>{{ $graceStarted?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('profile_photos.deadline') }}</dt>
                            <dd>{{ $deadline?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('profile_photos.uploaded_at') }}</dt>
                            <dd>{{ $uploadedAt?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                    <div class="data-card-actions">
                        @include('admin.profile-photos.partials.review-actions', [
                            'student' => $student,
                            'compact' => false,
                        ])
                    </div>
                </div>
            </article>
        @empty
            <p class="text-center text-muted-theme py-4 mb-0">{{ __('profile_photos.no_students') }}</p>
        @endforelse
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="profilePhotoReviewModal" tabindex="-1" aria-labelledby="profilePhotoReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="profilePhotoReviewModalLabel">{{ __('pages.profile_photo_modal_title') }}</h5>
                    <div class="small text-muted-theme" id="profilePhotoReviewModalEmail"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <p class="fw-semibold mb-3" id="profilePhotoReviewModalName"></p>
                    <img src="" alt="" id="profilePhotoReviewModalImage" class="img-fluid rounded profile-photo-review-image">
                </div>

                <div id="profilePhotoReviewPendingActions" class="d-none border-top pt-3">
                    <form method="POST" id="profilePhotoReviewApproveForm" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-check-lg"></i> {{ __('profile_photos.approve') }}
                        </button>
                    </form>
                    <form method="POST" id="profilePhotoReviewRejectForm"
                          data-confirm="{{ __('profile_photos.confirm_reject') }}">
                        @csrf
                        <input type="text" name="profile_photo_rejection_note" id="profilePhotoReviewRejectNote"
                               class="form-control form-control-sm mb-1"
                               placeholder="{{ __('profile_photos.rejection_note') }}">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-x-lg"></i> {{ __('profile_photos.reject') }}
                        </button>
                    </form>
                </div>

                <div id="profilePhotoReviewGraceActions" class="d-none border-top pt-3 mt-3 d-flex flex-column gap-2">
                    <form method="POST" id="profilePhotoReviewExtendForm" class="d-none d-flex flex-column flex-sm-row gap-1">
                        @csrf
                        <input type="datetime-local" name="profile_photo_deadline_at" id="profilePhotoReviewExtendDeadline"
                               class="form-control form-control-sm" required>
                        <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">
                            {{ __('profile_photos.extend_deadline') }}
                        </button>
                    </form>
                    <form method="POST" id="profilePhotoReviewResetForm" class="d-none"
                          data-confirm="{{ __('profile_photos.confirm_reset_grace') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-warning w-100">
                            {{ __('profile_photos.reset_grace') }}
                        </button>
                    </form>
                </div>

                <p id="profilePhotoReviewNoActions" class="d-none small text-muted-theme border-top pt-3 mb-0">
                    {{ __('profile_photos.no_actions_for_status') }}
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('pages.close') }}</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('styles')
<style>
.profile-photo-review-image {
    max-height: min(55vh, 420px);
    width: auto;
    max-width: 100%;
    object-fit: contain;
    border: 2px solid var(--color-surface-border, #dee2e6);
    background: var(--color-surface, #fff);
}
#profilePhotoReviewModal .modal-body {
    pointer-events: auto;
}
#profilePhotoReviewModal .modal-content {
    pointer-events: auto;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('profilePhotoReviewModal');
    if (!modal) return;

    const image = document.getElementById('profilePhotoReviewModalImage');
    const nameEl = document.getElementById('profilePhotoReviewModalName');
    const emailEl = document.getElementById('profilePhotoReviewModalEmail');
    const pendingBox = document.getElementById('profilePhotoReviewPendingActions');
    const graceBox = document.getElementById('profilePhotoReviewGraceActions');
    const noActions = document.getElementById('profilePhotoReviewNoActions');
    const approveForm = document.getElementById('profilePhotoReviewApproveForm');
    const rejectForm = document.getElementById('profilePhotoReviewRejectForm');
    const rejectNote = document.getElementById('profilePhotoReviewRejectNote');
    const extendForm = document.getElementById('profilePhotoReviewExtendForm');
    const extendDeadline = document.getElementById('profilePhotoReviewExtendDeadline');
    const resetForm = document.getElementById('profilePhotoReviewResetForm');

    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        const url = trigger.getAttribute('data-photo-url') || '';
        const name = trigger.getAttribute('data-photo-name') || '';
        const email = trigger.getAttribute('data-photo-email') || '';
        const canReview = trigger.getAttribute('data-can-approve-reject') === '1';
        const canExtend = trigger.getAttribute('data-can-extend') === '1';
        const canReset = trigger.getAttribute('data-can-reset') === '1';

        if (image) {
            image.src = url;
            image.alt = name;
        }
        if (nameEl) nameEl.textContent = name;
        if (emailEl) emailEl.textContent = email;
        if (rejectNote) rejectNote.value = '';
        if (extendDeadline) extendDeadline.value = '';

        if (approveForm) approveForm.action = trigger.getAttribute('data-approve-url') || '';
        if (rejectForm) rejectForm.action = trigger.getAttribute('data-reject-url') || '';
        if (extendForm) extendForm.action = trigger.getAttribute('data-extend-url') || '';
        if (resetForm) resetForm.action = trigger.getAttribute('data-reset-url') || '';

        if (pendingBox) pendingBox.classList.toggle('d-none', !canReview);
        if (extendForm) {
            extendForm.classList.toggle('d-none', !canExtend);
            extendForm.classList.toggle('d-flex', canExtend);
        }
        if (resetForm) resetForm.classList.toggle('d-none', !canReset);
        if (graceBox) graceBox.classList.toggle('d-none', !canExtend && !canReset);
        if (noActions) noActions.classList.toggle('d-none', canReview || canExtend || canReset);
    });

    modal.addEventListener('hidden.bs.modal', function () {
        if (image) {
            image.removeAttribute('src');
            image.alt = '';
        }
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            if (!document.querySelector('.modal.show')) {
                backdrop.remove();
            }
        });
        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
            document.body.removeAttribute('inert');
        }
    });
});
</script>
@endpush
