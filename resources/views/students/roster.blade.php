@extends('layouts.app')

@section('title', __('students.roster_title'))

@section('content')
<div class="container py-4 animate-in student-data-hub">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('students.roster_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('students.roster_intro') }}</p>
        </div>
        @if($course)
            <form method="POST" action="{{ route('students.roster.announce', $course) }}"
                  onsubmit="return confirm(@json(__('students.confirm_announce')))">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-envelope-heart"></i> {{ __('students.send_announcement') }}
                </button>
            </form>
        @endif
    </div>

@if($courses->isEmpty())
        <div class="app-tile text-center text-muted-theme py-5">{{ __('students.no_courses') }}</div>
    @else
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('students.roster') }}" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="course" class="form-label">{{ __('pages.course') }}</label>
                        <select name="course" id="course" class="form-select" onchange="this.form.submit()">
                            @foreach($courses as $c)
                                <option value="{{ $c->course_id }}" @selected($course && $course->course_id === $c->course_id)>
                                    {{ $c->title }}@if($c->year) ({{ $c->year }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="app-tile text-center py-2 mb-0">
                            <small class="text-muted-theme d-block">{{ __('students.total_students') }}</small>
                            <strong class="fs-4">{{ $students->count() }}</strong>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="app-card card shadow-sm h-100 border-warning border-opacity-50">
                    <div class="card-header bg-warning bg-opacity-10 fw-semibold">
                        <i class="bi bi-cake2"></i> {{ __('students.birthdays_this_month', ['month' => $thisMonthLabel]) }}
                        <span class="badge bg-warning text-dark ms-1">{{ $thisMonthBirthdays->count() }}</span>
                    </div>
                    <div class="card-body">
                        @forelse($thisMonthBirthdays as $student)
                            @include('students.partials.roster-student-card', [
                                'student' => $student,
                                'whatsappMessage' => __('students.whatsapp_birthday_message', ['name' => $student->displayName()]),
                                'showNationalId' => false,
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
                            @include('students.partials.roster-student-card', [
                                'student' => $student,
                                'showNationalId' => false,
                            ])
                        @empty
                            <p class="text-muted-theme mb-0">{{ __('students.no_birthdays_month') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="app-card card shadow-sm">
            <div class="card-header fw-semibold">
                <i class="bi bi-people"></i> {{ __('students.all_students') }}
            </div>
            <div class="card-body p-2 p-md-3">
                @forelse($students as $student)
                    @include('students.partials.roster-student-card', [
                        'student' => $student,
                        'showAge' => true,
                    ])
                @empty
                    <p class="text-muted-theme text-center py-4 mb-0">{{ __('students.no_students') }}</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
@endsection
