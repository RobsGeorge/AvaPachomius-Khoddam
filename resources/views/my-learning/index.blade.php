@extends('layouts.app')

@section('title', __('my_learning.title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:920px;">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('my_learning.title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('my_learning.subtitle') }}</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('calendar.ics') }}" class="btn btn-outline-secondary btn-sm" title="{{ __('calendar.download_hint') }}">
                <i class="bi bi-calendar-plus" aria-hidden="true"></i> {{ __('calendar.download') }}
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="bi bi-printer" aria-hidden="true"></i> {{ __('pages.print') }}
            </button>
        </div>
    </div>

    @if(empty($courseCards))
        <div class="app-card card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-mortarboard fs-1 text-muted-theme d-block mb-3" aria-hidden="true"></i>
                <h2 class="h5">{{ __('my_learning.empty_heading') }}</h2>
                <p class="text-muted-theme mb-0">{{ __('my_learning.empty_body') }}</p>
            </div>
        </div>
    @else
        <div class="d-flex flex-column gap-4">
            @foreach($courseCards as $card)
                @php($course = $card['course'])
                <div class="app-card card shadow-sm">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span class="fw-semibold">
                            <i class="bi bi-journal-bookmark" aria-hidden="true"></i>
                            {{ $course->title }} <span class="text-muted-theme">— {{ $course->year }}</span>
                        </span>
                        @if($card['graduated'])
                            <span class="badge bg-success">{{ __('my_learning.graduated') }}</span>
                        @elseif($card['grades_announced'])
                            <span class="badge bg-secondary">{{ __('my_learning.not_graduated') }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- Grades --}}
                            <div class="col-sm-4">
                                <div class="text-muted-theme small">{{ __('my_learning.grades') }}</div>
                                @if($card['grades_announced'] && $card['has_record'])
                                    <div class="fs-5 fw-semibold">
                                        {{ $card['letter_grade'] ?? '—' }}
                                        @if(!is_null($card['final_grade']))
                                            <span class="text-muted-theme fs-6">({{ rtrim(rtrim(number_format((float) $card['final_grade'], 1), '0'), '.') }})</span>
                                        @endif
                                    </div>
                                @else
                                    <div class="text-muted-theme">{{ __('my_learning.grades_pending') }}</div>
                                @endif
                            </div>
                            {{-- Attendance --}}
                            <div class="col-sm-4">
                                <div class="text-muted-theme small">{{ __('my_learning.attendance_pct') }}</div>
                                <div class="fs-5 fw-semibold">
                                    {{ !is_null($card['attendance_pct']) ? number_format((float) $card['attendance_pct'], 0).'%' : '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @if($card['grades_url'])
                                <a href="{{ $card['grades_url'] }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-clipboard-data" aria-hidden="true"></i> {{ __('my_learning.view_grades') }}
                                </a>
                            @endif
                            <a href="{{ $card['attendance_url'] }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-calendar-check" aria-hidden="true"></i> {{ __('my_learning.view_attendance') }}
                            </a>
                            @if($card['certificate_url'])
                                <a href="{{ $card['certificate_url'] }}" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-award" aria-hidden="true"></i> {{ __('my_learning.download_certificate') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
