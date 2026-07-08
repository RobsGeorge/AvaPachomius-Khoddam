@extends('layouts.app')

@section('title', __('pages.profile_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <h2 class="page-title mb-4">{{ __('pages.profile_title') }}</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card card mb-4">
        <div class="card-body text-center">
            <div class="relative w-32 h-32 rounded-full overflow-hidden mx-auto mb-4 cursor-pointer" style="width:128px;height:128px;margin:0 auto 1rem;">
                @if($user->profile_photo)
                    <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ __('pages.profile_photo') }}"
                        class="w-100 h-100 object-fit-cover border rounded-circle" style="object-fit:cover;width:100%;height:100%;">
                @else
                    <div class="w-100 h-100 bg-light d-flex align-items-center justify-content-center rounded-circle" style="width:100%;height:100%;">
                        <span class="text-muted-theme">{{ __('pages.no_photo') }}</span>
                    </div>
                @endif

                <form action="{{ route('profile.picture.update') }}" method="POST" enctype="multipart/form-data" class="position-absolute top-0 start-0 w-100 h-100">
                    @csrf
                    @method('PUT')
                    <input type="file" name="profile_photo"
                        class="opacity-0 w-100 h-100 cursor-pointer"
                        style="opacity:0;width:100%;height:100%;cursor:pointer;"
                        onchange="this.form.submit()"
                        title="{{ __('pages.upload_new_photo') }}" />
                </form>
            </div>

            <div class="text-start">
                <p><strong>{{ __('pages.full_name') }}:</strong> {{ $fullName }}</p>
                <p><strong>{{ __('pages.birth_date') }}:</strong>
                    {{ $user->date_of_birth?->format('Y-m-d') ?? __('pages.not_available') }}</p>
                <p><strong>{{ __('pages.national_id') }}:</strong> {{ $user->national_id ?? __('pages.not_available') }}</p>
                <p><strong>{{ __('pages.phone') }}:</strong> 0{{ $user->mobile_number ?? __('pages.not_available') }}</p>
                <p><strong>{{ __('pages.job') }}:</strong> {{ $user->job ?? __('pages.not_available') }}</p>
                <p><strong>{{ __('pages.email') }}:</strong> {{ $user->email }}</p>
                <p><strong>{{ __('pages.registration_date') }}:</strong>
                    {{ $user->created_at?->format('Y-m-d') ?? __('pages.not_available') }}</p>
            </div>
        </div>
    </div>

    <div class="app-card card text-center">
        <div class="card-body">
            <h3 class="page-title h5 mb-4">{{ __('pages.qr_attendance_title') }}</h3>
            <div class="d-inline-block p-4 bg-white border rounded shadow qr-attendance-trigger"
                 role="button"
                 tabindex="0"
                 data-bs-toggle="modal"
                 data-bs-target="#qrModal"
                 aria-label="{{ __('pages.scan_qr_attendance') }}">
                {!! QrCode::size(200)->generate($attendanceUrl) !!}
            </div>
            <p class="mt-2 text-muted-theme small">{{ __('pages.scan_qr_attendance') }}</p>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrModalLabel">{{ __('pages.qr_attendance_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
            </div>
            <div class="modal-body text-center">
                {!! QrCode::size(280)->generate($attendanceUrl) !!}
                <p class="mt-3 mb-0 text-muted-theme small">{{ __('pages.scan_qr_attendance') }}</p>
            </div>
        </div>
    </div>
</div>
@endpush
