@extends('layouts.app')

@section('title', __('course_graduation.final_grades_title'))

@section('content')
@php
    $color = \App\Models\GradeCategory::gradeColor($record->final_total_grade);
    $letterAr = \App\Models\GradeCategory::letterGradeAr($record->final_total_grade);
    $details = $record->grades_detail_json ?? [];
@endphp
<div class="container py-4 animate-in">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('course_graduation.final_grades_title') }}</h1>
        <p class="text-muted small mb-0">{{ __('course_graduation.final_grades_subtitle', ['course' => $course->title.' — '.$course->year]) }}</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="app-card card shadow-sm text-center border-{{ $color }}">
                <div class="card-body py-3">
                    <div class="small text-muted-theme mb-1">{{ __('course_graduation.final_grade') }}</div>
                    <div class="display-5 fw-bold text-{{ $color }}">{{ number_format($record->final_total_grade, 1) }}<small class="fs-5 text-muted">/100</small></div>
                    <div class="mt-1">
                        <span class="badge bg-{{ $color }} fs-6 px-3">{{ $record->letter_grade }} — {{ $letterAr }}</span>
                    </div>
                    @if($record->grace_marks_applied > 0)
                        <div class="small text-muted mt-2">
                            {{ __('course_graduation.raw_grade') }}: {{ number_format($record->raw_total_grade, 1) }}
                            · {{ __('course_graduation.grace_applied') }}: +{{ number_format($record->grace_marks_applied, 1) }}
                        </div>
                    @endif
                    <div class="mt-2">
                        @if($record->graduated)
                            <span class="badge bg-success">{{ __('course_graduation.graduated') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ __('course_graduation.not_graduated') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted-theme fw-semibold mb-2">{{ __('pages.attendance_percentage') }}</div>
                    <div class="fs-4 fw-bold mb-3">{{ number_format($record->attendance_pct, 1) }}%</div>
                    @if($record->graduated && $record->certificate)
                        <a href="{{ route('certificates.download', $record->certificate->certificate_uuid) }}" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-award"></i> {{ __('course_graduation.download_certificate') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(! empty($details['categories']))
        @foreach($details['categories'] as $cat)
            <div class="app-card card shadow-sm mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between">
                    <span>{{ $cat['name'] }}</span>
                    <span class="text-muted small">{{ number_format($cat['contribution'] ?? 0, 1) }}% ({{ $cat['weight'] ?? 0 }}%)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('pages.item') }}</th>
                                    <th class="text-center">{{ __('pages.score') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cat['items'] ?? [] as $item)
                                    <tr>
                                        <td>{{ $item['title'] }}</td>
                                        <td class="text-center">
                                            @if($item['score'] !== null)
                                                {{ number_format($item['score'], 1) }} / {{ number_format($item['max_score'], 1) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
