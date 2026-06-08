@extends('layouts.app')

@section('title', __('demo.title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:720px;">
  <div class="text-center mb-4">
    <span class="badge bg-info text-dark mb-2">{{ __('demo.badge') }}</span>
    <h1 class="page-title mb-2">{{ __('demo.title') }}</h1>
    <p class="text-muted-theme">{{ __('demo.subtitle') }}</p>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="app-card card shadow-sm mb-4 border-primary">
    <div class="card-body p-4 text-center">
      <i class="bi bi-mortarboard display-4 text-primary mb-3"></i>
      <h2 class="h4 mb-2">{{ __('demo.student_heading') }}</h2>
      <p class="text-muted-theme small mb-4">{{ __('demo.student_body') }}</p>
      <form method="POST" action="{{ route('demo.enter', 'student') }}">
        @csrf
        <button type="submit" class="btn btn-primary btn-lg w-100 w-sm-auto px-5">
          <i class="bi bi-box-arrow-in-right"></i> {{ __('demo.enter_student') }}
        </button>
      </form>
      <p class="small text-muted-theme mt-3 mb-0">{{ __('demo.no_password') }}</p>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="app-card card h-100">
        <div class="card-body">
          <h3 class="h6 fw-semibold">{{ __('demo.instructor_heading') }}</h3>
          <p class="small text-muted-theme">{{ __('demo.other_roles_hint') }}</p>
          <form method="POST" action="{{ route('demo.enter', 'instructor') }}">
            @csrf
            <button type="submit" class="btn btn-outline-theme btn-sm w-100">{{ __('demo.enter_instructor') }}</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="app-card card h-100">
        <div class="card-body">
          <h3 class="h6 fw-semibold">{{ __('demo.admin_heading') }}</h3>
          <p class="small text-muted-theme">{{ __('demo.other_roles_hint') }}</p>
          <form method="POST" action="{{ route('demo.enter', 'admin') }}">
            @csrf
            <button type="submit" class="btn btn-outline-theme btn-sm w-100">{{ __('demo.enter_admin') }}</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-light border mt-4 small text-muted-theme mb-0">
    <i class="bi bi-info-circle"></i> {{ __('demo.data_notice') }}
  </div>
</div>
@endsection
