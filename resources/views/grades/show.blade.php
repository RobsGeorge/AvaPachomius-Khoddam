@extends('layouts.app')

@section('title', __('pages.my_grades'))

@section('content')
<div class="container py-4 animate-in">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title mb-0">{{ __('pages.my_grades') }}</h1>
            <small class="text-muted fw-semibold">{{ $course->title }} — {{ $course->year }}</small>
        </div>
    </div>

    {{-- Total grade hero --}}
    @php
        $letter   = \App\Models\GradeCategory::letterGrade($total);
        $letterAr = \App\Models\GradeCategory::letterGradeAr($total);
        $color    = \App\Models\GradeCategory::gradeColor($total);
        $totalWeight = $course->gradeCategories->sum('weight_percentage');
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="app-card card shadow-sm text-center border-{{ $color }}">
                <div class="card-body py-3">
                    <div class="small text-muted-theme mb-1">{{ __('pages.overall_grade') }}</div>
                    <div class="display-5 fw-bold text-{{ $color }}">{{ number_format($total, 1) }}<small class="fs-5 text-muted">/100</small></div>
                    <div class="mt-1">
                        <span class="badge bg-{{ $color }} fs-6 px-3">{{ $letter }} — {{ $letterAr }}</span>
                    </div>
                    <div class="mt-2">
                        <div class="progress" style="height:10px;">
                            <div class="progress-bar bg-{{ $color }}" style="width:{{ min($total, 100) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted-theme fw-semibold mb-2">{{ __('pages.category_summary') }}</div>
                    @foreach($course->gradeCategories as $cat)
                        @php
                            $contrib = $cat->studentContribution($userId);
                            $catPct  = $cat->studentCategoryPercentage($userId);
                            $catColor = \App\Models\GradeCategory::$typeColors[$cat->type] ?? 'secondary';
                        @endphp
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>
                                    <i class="bi {{ \App\Models\GradeCategory::$typeIcons[$cat->type] ?? 'bi-dot' }} text-{{ $catColor }}"></i>
                                    {{ $cat->name }}
                                    <span class="text-muted">({{ number_format($cat->weight_percentage, 0) }}%)</span>
                                </span>
                                <span class="fw-semibold">
                                    {{ number_format($contrib, 1) }} / {{ number_format($cat->weight_percentage, 1) }}
                                </span>
                            </div>
                            <div class="progress" style="height:5px;">
                                <div class="progress-bar bg-{{ $catColor }}"
                                     style="width:{{ $cat->weight_percentage > 0 ? min($catPct, 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Detailed breakdown per category --}}
    @foreach($course->gradeCategories as $cat)
        @php
            $catColor = \App\Models\GradeCategory::$typeColors[$cat->type] ?? 'secondary';
            $catIcon  = \App\Models\GradeCategory::$typeIcons[$cat->type] ?? 'bi-three-dots';
            $rawScore = $cat->studentRawScore($userId);
            $maxScore = $cat->maxRawScore();
            $contrib  = $cat->studentContribution($userId);
            $catPct   = $cat->studentCategoryPercentage($userId);
        @endphp
        <div class="app-card card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center bg-{{ $catColor }} bg-opacity-10">
                <span class="fw-bold">
                    <i class="bi {{ $catIcon }} text-{{ $catColor }}"></i>
                    {{ $cat->name }}
                    <span class="badge bg-{{ $catColor }} ms-1">{{ number_format($cat->weight_percentage, 1) }}%</span>
                </span>
                <div class="text-end">
                    <div class="fw-semibold">{{ number_format($rawScore, 1) }} / {{ number_format($maxScore, 1) }}</div>
                    <small class="text-muted-theme">{{ number_format($catPct, 1) }}% — {{ __('pages.contribution') }} <strong>{{ number_format($contrib, 2) }}</strong></small>
                </div>
            </div>

            @if($cat->items->isNotEmpty())
                <div class="student-data-hub p-3">
                    @foreach($cat->items as $item)
                        @php
                            $grade = $item->grades->firstWhere('user_id', $userId);
                            $itemPct = ($item->max_score > 0 && $grade && $grade->score !== null)
                                ? round(($grade->score / $item->max_score) * 100, 1) : null;
                        @endphp
                        <article class="data-card">
                            <div class="data-card-title">
                                {{ $item->title }}
                                @if($item->description)
                                    <i class="bi bi-info-circle text-muted ms-1" title="{{ $item->description }}"></i>
                                @endif
                            </div>
                            <dl class="data-meta-list mb-0">
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.date') }}</dt>
                                    <dd>{{ $item->item_date ? $item->item_date->format('Y-m-d') : '—' }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.your_score') }}</dt>
                                    <dd>
                                        @if($grade && $grade->score !== null)
                                            <span class="text-{{ \App\Models\GradeCategory::gradeColor($itemPct) }} fw-semibold">
                                                {{ number_format($grade->score, 1) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.max_score') }}</dt>
                                    <dd>{{ number_format($item->max_score, 1) }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.percentage') }}</dt>
                                    <dd>
                                        @if($itemPct !== null)
                                            <span class="badge bg-{{ \App\Models\GradeCategory::gradeColor($itemPct) }}">{{ $itemPct }}%</span>
                                        @else
                                            <span class="text-muted-theme small">{{ __('pages.not_corrected') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.grader_notes') }}</dt>
                                    <dd class="text-muted small">{{ $grade?->notes ?? '—' }}</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                    <div class="data-card border-{{ $catColor }} bg-{{ $catColor }} bg-opacity-10">
                        <dl class="data-meta-list mb-0">
                            <div class="data-meta-row">
                                <dt class="fw-bold">{{ __('pages.total') }}</dt>
                                <dd class="fw-semibold">{{ number_format($rawScore, 1) }} / {{ number_format($maxScore, 1) }}</dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.percentage') }}</dt>
                                <dd><span class="badge bg-{{ $catColor }}">{{ number_format($catPct, 1) }}%</span></dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.contribution') }}</dt>
                                <dd>{{ number_format($contrib, 2) }} / {{ number_format($cat->weight_percentage, 1) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            @else
                <div class="card-body text-muted-theme small py-2">{{ __('pages.no_items_in_category') }}</div>
            @endif
        </div>
    @endforeach

    {{-- Grand total footer --}}
    <div class="app-card card border-{{ $color }} shadow">
        <div class="card-body d-flex justify-content-between align-items-center py-3">
            <span class="fs-5 fw-bold page-title h5 mb-0">{{ __('pages.grand_total') }}</span>
            <div class="text-end">
                <span class="display-6 fw-bold text-{{ $color }}">{{ number_format($total, 2) }} / 100</span>
                <div><span class="badge bg-{{ $color }} fs-6 px-3">{{ $letter }} — {{ $letterAr }}</span></div>
            </div>
        </div>
    </div>

</div>
@endsection
