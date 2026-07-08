@extends('layouts.app')

@section('title', __('students.birthdays_title'))

@section('content')
<div class="container py-4 animate-in student-data-hub">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('students.birthdays_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('students.birthdays_intro') }}</p>
    </div>

    @if($courses->isEmpty())
        <div class="app-tile text-center text-muted-theme py-5">{{ __('students.no_courses') }}</div>
    @else
        @if($courses->count() > 1)
            <div class="app-card card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('students.birthdays') }}">
                        <label for="course" class="form-label">{{ __('pages.course') }}</label>
                        <select name="course" id="course" class="form-select" onchange="this.form.submit()">
                            @foreach($courses as $c)
                                <option value="{{ $c->course_id }}" @selected($course && $course->course_id === $c->course_id)>
                                    {{ $c->title }}@if($c->year) ({{ $c->year }})@endif
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="app-card card shadow-sm h-100 border-warning border-opacity-50">
                    <div class="card-header bg-warning bg-opacity-10 fw-semibold">
                        <i class="bi bi-cake2"></i> {{ __('students.birthdays_this_month', ['month' => $thisMonthLabel]) }}
                        <span class="badge bg-warning text-dark ms-1">{{ $thisMonthBirthdays->count() }}</span>
                    </div>
                    <div class="card-body">
                        @forelse($thisMonthBirthdays as $student)
                            @include('students.partials.birthday-peer-card', [
                                'student' => $student,
                                'whatsappMessage' => __('students.whatsapp_birthday_message', ['name' => $student->displayName()]),
                            ])
                        @empty
                            <p class="text-muted-theme mb-0">{{ __('students.no_birthdays_month') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="app-card card shadow-sm h-100">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-calendar-event"></i> {{ __('students.birthdays_next_month', ['month' => $nextMonthLabel]) }}
                        <span class="badge bg-secondary ms-1">{{ $nextMonthBirthdays->count() }}</span>
                    </div>
                    <div class="card-body">
                        @forelse($nextMonthBirthdays as $student)
                            @include('students.partials.birthday-peer-card', ['student' => $student])
                        @empty
                            <p class="text-muted-theme mb-0">{{ __('students.no_birthdays_month') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
