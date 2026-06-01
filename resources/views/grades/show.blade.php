@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">درجاتي</h1>
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
            <div class="card shadow-sm text-center border-{{ $color }}">
                <div class="card-body py-3">
                    <div class="small text-muted mb-1">الدرجة الإجمالية</div>
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
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted fw-semibold mb-2">ملخص الفئات</div>
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
        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center bg-{{ $catColor }} bg-opacity-10">
                <span class="fw-bold">
                    <i class="bi {{ $catIcon }} text-{{ $catColor }}"></i>
                    {{ $cat->name }}
                    <span class="badge bg-{{ $catColor }} ms-1">{{ number_format($cat->weight_percentage, 1) }}%</span>
                </span>
                <div class="text-end">
                    <div class="fw-semibold">{{ number_format($rawScore, 1) }} / {{ number_format($maxScore, 1) }}</div>
                    <small class="text-muted">{{ number_format($catPct, 1) }}% — مساهمة: <strong>{{ number_format($contrib, 2) }}</strong></small>
                </div>
            </div>

            @if($cat->items->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>البند</th>
                                <th>التاريخ</th>
                                <th class="text-center">درجتك</th>
                                <th class="text-center">الدرجة القصوى</th>
                                <th class="text-center">النسبة</th>
                                <th>ملاحظات المصحح</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cat->items as $item)
                                @php
                                    $grade = $item->grades->firstWhere('user_id', $userId);
                                    $itemPct = ($item->max_score > 0 && $grade && $grade->score !== null)
                                        ? round(($grade->score / $item->max_score) * 100, 1) : null;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $item->title }}
                                        @if($item->description)
                                            <i class="bi bi-info-circle text-muted ms-1" title="{{ $item->description }}"></i>
                                        @endif
                                    </td>
                                    <td class="text-muted small">{{ $item->item_date ? $item->item_date->format('Y-m-d') : '—' }}</td>
                                    <td class="text-center fw-semibold">
                                        @if($grade && $grade->score !== null)
                                            <span class="text-{{ \App\Models\GradeCategory::gradeColor($itemPct) }}">
                                                {{ number_format($grade->score, 1) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-muted">{{ number_format($item->max_score, 1) }}</td>
                                    <td class="text-center">
                                        @if($itemPct !== null)
                                            <span class="badge bg-{{ \App\Models\GradeCategory::gradeColor($itemPct) }}">
                                                {{ $itemPct }}%
                                            </span>
                                        @else
                                            <span class="text-muted small">لم يُصحَّح</span>
                                        @endif
                                    </td>
                                    <td class="text-muted small">{{ $grade?->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="2">المجموع</td>
                                <td class="text-center">{{ number_format($rawScore, 1) }}</td>
                                <td class="text-center">{{ number_format($maxScore, 1) }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $catColor }}">{{ number_format($catPct, 1) }}%</span>
                                </td>
                                <td>مساهمة: {{ number_format($contrib, 2) }} / {{ number_format($cat->weight_percentage, 1) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="card-body text-muted small py-2">لا توجد بنود بعد لهذه الفئة.</div>
            @endif
        </div>
    @endforeach

    {{-- Grand total footer --}}
    <div class="card border-{{ $color }} shadow">
        <div class="card-body d-flex justify-content-between align-items-center py-3">
            <span class="fs-5 fw-bold">المجموع الكلي</span>
            <div class="text-end">
                <span class="display-6 fw-bold text-{{ $color }}">{{ number_format($total, 2) }} / 100</span>
                <div><span class="badge bg-{{ $color }} fs-6 px-3">{{ $letter }} — {{ $letterAr }}</span></div>
            </div>
        </div>
    </div>

</div>
@endsection
