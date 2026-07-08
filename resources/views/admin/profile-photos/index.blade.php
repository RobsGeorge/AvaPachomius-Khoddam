@extends('layouts.app')

@section('title', __('profile_photos.report_title'))

@section('content')
@php
    use App\Services\ProfilePhotoGateService;
    $gate = app(ProfilePhotoGateService::class);
@endphp
<div class="container-fluid py-4 animate-in student-data-hub">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('profile_photos.report_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('profile_photos.report_intro') }}</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

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
                    @php($status = $gate->reportStatus($student))
                    @php($deadline = $gate->deadlineFor($student))
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if($student->profile_photo)
                                    <button type="button" class="btn p-0 border-0 student-photo-trigger"
                                            data-bs-toggle="modal" data-bs-target="#studentPhotoModal"
                                            data-photo-url="{{ asset('storage/' . $student->profile_photo) }}"
                                            data-photo-name="{{ $student->displayName() }}">
                                        <img src="{{ asset('storage/' . $student->profile_photo) }}" alt="" class="rounded-circle" width="40" height="40" style="object-fit:cover;">
                                    </button>
                                @endif
                                <div>
                                    <strong>{{ $student->displayName() }}</strong>
                                    <div class="small text-muted-theme">{{ $student->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-secondary">{{ __('profile_photos.status_'.$status) }}</span></td>
                        <td>{{ $student->profile_photo_grace_started_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $deadline?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $student->profile_photo_uploaded_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>
                            <div class="d-flex flex-column gap-2">
                                @if($student->isProfilePhotoPending())
                                    <form method="POST" action="{{ route('admin.profile-photos.approve', $student) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">{{ __('profile_photos.approve') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.profile-photos.reject', $student) }}" class="d-inline">
                                        @csrf
                                        <input type="text" name="profile_photo_rejection_note" class="form-control form-control-sm mb-1"
                                               placeholder="{{ __('profile_photos.rejection_note') }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('profile_photos.reject') }}</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.profile-photos.extend-deadline', $student) }}" class="d-flex gap-1 flex-wrap">
                                    @csrf
                                    <input type="datetime-local" name="profile_photo_deadline_at" class="form-control form-control-sm" required>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('profile_photos.extend_deadline') }}</button>
                                </form>

                                <form method="POST" action="{{ route('admin.profile-photos.reset-grace', $student) }}"
                                      data-confirm="{{ __('profile_photos.confirm_reset_grace') }}"
                                      onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('profile_photos.reset_grace') }}</button>
                                </form>
                            </div>
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
            @php($status = $gate->reportStatus($student))
            @php($deadline = $gate->deadlineFor($student))
            <article class="data-card app-card card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        @if($student->profile_photo)
                            <button type="button" class="btn p-0 border-0 student-photo-trigger"
                                    data-bs-toggle="modal" data-bs-target="#studentPhotoModal"
                                    data-photo-url="{{ asset('storage/' . $student->profile_photo) }}"
                                    data-photo-name="{{ $student->displayName() }}">
                                <img src="{{ asset('storage/' . $student->profile_photo) }}" alt="" class="rounded-circle" width="48" height="48" style="object-fit:cover;">
                            </button>
                        @endif
                        <div>
                            <div class="data-card-title mb-0">{{ $student->displayName() }}</div>
                            <div class="small text-muted-theme">{{ $student->email }}</div>
                        </div>
                    </div>
                    <dl class="data-meta-list mb-3">
                        <div class="data-meta-row">
                            <dt>{{ __('profile_photos.status') }}</dt>
                            <dd><span class="badge bg-secondary">{{ __('profile_photos.status_'.$status) }}</span></dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('profile_photos.grace_started') }}</dt>
                            <dd>{{ $student->profile_photo_grace_started_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('profile_photos.deadline') }}</dt>
                            <dd>{{ $deadline?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('profile_photos.uploaded_at') }}</dt>
                            <dd>{{ $student->profile_photo_uploaded_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                    <div class="data-card-actions d-flex flex-column gap-2">
                        @if($student->isProfilePhotoPending())
                            <form method="POST" action="{{ route('admin.profile-photos.approve', $student) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success w-100">{{ __('profile_photos.approve') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.profile-photos.reject', $student) }}">
                                @csrf
                                <input type="text" name="profile_photo_rejection_note" class="form-control form-control-sm mb-1"
                                       placeholder="{{ __('profile_photos.rejection_note') }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">{{ __('profile_photos.reject') }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.profile-photos.extend-deadline', $student) }}" class="d-flex flex-column gap-1">
                            @csrf
                            <input type="datetime-local" name="profile_photo_deadline_at" class="form-control form-control-sm" required>
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">{{ __('profile_photos.extend_deadline') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.profile-photos.reset-grace', $student) }}"
                              data-confirm="{{ __('profile_photos.confirm_reset_grace') }}"
                              onsubmit="return confirm(this.dataset.confirm)">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning w-100">{{ __('profile_photos.reset_grace') }}</button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="text-center text-muted-theme py-4 mb-0">{{ __('profile_photos.no_students') }}</p>
        @endforelse
    </div>
</div>
@endsection
