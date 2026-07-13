@extends('layouts.app')

@section('title', __('pages.enter_scores'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4 admin-page-header">
        <div>
            <h1 class="page-title mb-0">{{ __('pages.enter_scores') }}</h1>
            <small class="text-muted-theme">
                <a href="{{ route('grades.admin', $course->course_id) }}">{{ $course->title }}</a>
                / {{ $item->category->name }} / {{ $item->title }}
            </small>
        </div>
        <a href="{{ route('grades.admin', $course->course_id) }}" class="btn btn-outline-theme btn-sm">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back') }}
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="app-tile text-center">
                <div class="small text-muted-theme">{{ __('pages.max_score') }}</div>
                <div class="fs-4 fw-bold text-primary">{{ number_format($item->max_score, 1) }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="app-tile text-center">
                <div class="small text-muted-theme">{{ __('pages.date') }}</div>
                <div class="fw-semibold">{{ $item->item_date ? $item->item_date->format('Y-m-d') : '—' }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="app-tile text-center">
                <div class="small text-muted-theme">{{ __('pages.students') }}</div>
                <div class="fs-4 fw-bold">{{ $students->count() }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="app-tile text-center">
                <div class="small text-muted-theme">{{ __('pages.corrected_count') }}</div>
                <div class="fs-4 fw-bold text-success">{{ $existingGrades->whereNotNull('score')->count() }}</div>
            </div>
        </div>
    </div>

<form method="POST" action="{{ route('grade-items.scores.save', $item->item_id) }}" id="grade-item-scores-form">
        @csrf
        <div class="app-card card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">{{ __('pages.students_list') }}</span>
                <button type="submit" class="btn btn-success btn-sm d-none d-lg-inline-flex">
                    <i class="bi bi-save"></i> {{ __('pages.save_all_grades') }}
                </button>
            </div>
            <div class="table-responsive d-none d-lg-block admin-table-desktop">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.number') }}</th>
                            <th>{{ __('pages.student') }}</th>
                            <th>{{ __('pages.national_id_short') }}</th>
                            <th style="width:160px;">
                                {{ __('pages.grade') }} <span class="text-muted-theme small">/ {{ number_format($item->max_score, 1) }}</span>
                            </th>
                            <th>{{ __('pages.notes') }}</th>
                            <th>{{ __('pages.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $i => $student)
                            @php $grade = $existingGrades->get($student->user_id); @endphp
                            <tr class="{{ $grade && $grade->score !== null ? '' : 'table-warning bg-opacity-25' }}">
                                <td class="text-muted-theme small">{{ $i + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $student->first_name }} {{ $student->second_name }} {{ $student->third_name }}
                                    </div>
                                </td>
                                <td class="text-muted-theme small">{{ $student->national_id }}</td>
                                <td>
                                    <input type="number"
                                           name="scores[{{ $student->user_id }}]"
                                           class="form-control form-control-sm"
                                           value="{{ $grade ? $grade->score : '' }}"
                                           min="0" max="{{ $item->max_score }}" step="0.5"
                                           placeholder="—">
                                </td>
                                <td>
                                    <input type="text"
                                           name="notes[{{ $student->user_id }}]"
                                           class="form-control form-control-sm"
                                           value="{{ $grade ? $grade->notes : '' }}"
                                           placeholder="{{ __('pages.optional_note') }}" maxlength="255">
                                </td>
                                <td>
                                    @if($grade && $grade->score !== null)
                                        <span class="badge bg-success">
                                            {{ number_format($grade->score, 1) }} / {{ number_format($item->max_score, 1) }}
                                        </span>
                                        @if($grade->graded_at)
                                            <div class="text-muted-theme" style="font-size:0.7rem;">
                                                {{ $grade->graded_at->format('Y-m-d H:i') }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary">{{ __('pages.not_corrected') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted-theme py-4">{{ __('pages.no_students_in_course') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-lg-none admin-data-cards student-data-hub p-3">
                @forelse($students as $i => $student)
                    @php $grade = $existingGrades->get($student->user_id); @endphp
                    <article class="data-card {{ $grade && $grade->score !== null ? '' : 'border-warning' }}">
                        <div class="data-card-title">
                            <span class="text-muted-theme small me-1">#{{ $i + 1 }}</span>
                            {{ $student->first_name }} {{ $student->second_name }} {{ $student->third_name }}
                        </div>
                        <dl class="data-meta-list mb-3">
                            <div class="data-meta-row">
                                <dt>{{ __('pages.national_id_short') }}</dt>
                                <dd>{{ $student->national_id }}</dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.grade') }}</dt>
                                <dd>
                                    <input type="number"
                                           name="scores[{{ $student->user_id }}]"
                                           class="form-control form-control-sm"
                                           value="{{ $grade ? $grade->score : '' }}"
                                           min="0" max="{{ $item->max_score }}" step="0.5"
                                           placeholder="— / {{ number_format($item->max_score, 1) }}">
                                </dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.notes') }}</dt>
                                <dd>
                                    <input type="text"
                                           name="notes[{{ $student->user_id }}]"
                                           class="form-control form-control-sm"
                                           value="{{ $grade ? $grade->notes : '' }}"
                                           placeholder="{{ __('pages.optional_note') }}" maxlength="255">
                                </dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.status') }}</dt>
                                <dd>
                                    @if($grade && $grade->score !== null)
                                        <span class="badge bg-success">
                                            {{ number_format($grade->score, 1) }} / {{ number_format($item->max_score, 1) }}
                                        </span>
                                        @if($grade->graded_at)
                                            <div class="text-muted-theme small mt-1">
                                                {{ $grade->graded_at->format('Y-m-d H:i') }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary">{{ __('pages.not_corrected') }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </article>
                @empty
                    <p class="text-center text-muted-theme py-4 mb-0">{{ __('pages.no_students_in_course') }}</p>
                @endforelse
            </div>

            <div class="card-footer admin-mobile-form-footer">
                <button type="submit" class="btn btn-success w-100 w-lg-auto">
                    <i class="bi bi-save"></i> {{ __('pages.save_all_grades') }}
                </button>
            </div>
        </div>
    </form>
    <script>
        document.getElementById('grade-item-scores-form')?.addEventListener('submit', function () {
            const useDesktop = window.matchMedia('(min-width: 992px)').matches;
            const disableRoot = useDesktop ? this.querySelector('.d-lg-none') : this.querySelector('.admin-table-desktop');
            disableRoot?.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = true; });
        });
    </script>
</div>
@endsection
