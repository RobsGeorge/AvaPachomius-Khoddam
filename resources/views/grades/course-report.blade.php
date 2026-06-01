@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">تقرير الدرجات</h1>
            <small class="text-muted fw-semibold">{{ $course->title }} — {{ $course->year }}</small>
        </div>
        <a href="{{ route('grades.admin', $course->course_id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-gear"></i> إدارة التقييم
        </a>
    </div>

    {{-- Summary stats --}}
    @php
        $passed  = $report->filter(fn ($r) => $r['total'] >= 60)->count();
        $failed  = $report->count() - $passed;
        $highest = $report->max('total') ?? 0;
        $lowest  = $report->min('total') ?? 0;
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body py-2">
                    <div class="small text-muted">عدد الطلاب</div>
                    <div class="fs-3 fw-bold">{{ $report->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body py-2">
                    <div class="small text-muted">متوسط الدرجات</div>
                    <div class="fs-3 fw-bold text-primary">{{ number_format($avg, 1) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body py-2">
                    <div class="small text-muted">ناجح / راسب</div>
                    <div class="fs-3 fw-bold">
                        <span class="text-success">{{ $passed }}</span>
                        <span class="text-muted">/</span>
                        <span class="text-danger">{{ $failed }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body py-2">
                    <div class="small text-muted">أعلى / أدنى</div>
                    <div class="fs-5 fw-bold">
                        <span class="text-success">{{ number_format($highest, 1) }}</span>
                        <span class="text-muted"> / </span>
                        <span class="text-danger">{{ number_format($lowest, 1) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Grade distribution --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold small">توزيع الدرجات</div>
        <div class="card-body py-2 d-flex gap-3 flex-wrap">
            @foreach(['A+'=>[95,100], 'A'=>[90,95], 'B+'=>[85,90], 'B'=>[80,85], 'C+'=>[75,80], 'C'=>[70,75], 'D'=>[60,70], 'F'=>[0,60]] as $ltr => [$lo, $hi])
                @php $cnt = $report->filter(fn($r) => $r['total'] >= $lo && $r['total'] < $hi)->count(); @endphp
                @if($ltr === 'F') @php $cnt = $report->filter(fn($r) => $r['total'] < 60)->count(); @endphp @endif
                <div class="text-center px-2">
                    <div class="badge bg-{{ $ltr === 'F' ? 'danger' : ($lo >= 85 ? 'success' : ($lo >= 70 ? 'primary' : ($lo >= 60 ? 'warning' : 'danger'))) }} fs-6">{{ $ltr }}</div>
                    <div class="fw-bold">{{ $cnt }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Full table --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>الطالب</th>
                            @foreach($course->gradeCategories as $cat)
                                <th class="text-center" title="{{ $cat->name }} ({{ $cat->weight_percentage }}%)">
                                    {{ Str::limit($cat->name, 12) }}<br>
                                    <span class="text-muted" style="font-size:0.7rem;">{{ $cat->weight_percentage }}%</span>
                                </th>
                            @endforeach
                            <th class="text-center">الإجمالي</th>
                            <th class="text-center">التقدير</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report as $i => $row)
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $row['user']->first_name }} {{ $row['user']->second_name }}
                                    </div>
                                    <div class="text-muted" style="font-size:0.75rem;">{{ $row['user']->national_id }}</div>
                                </td>
                                @foreach($row['categories'] as $catRow)
                                    <td class="text-center">
                                        <div class="fw-semibold">{{ number_format($catRow['contribution'], 1) }}</div>
                                        <div class="text-muted" style="font-size:0.7rem;">
                                            {{ number_format($catRow['raw'], 1) }}/{{ number_format($catRow['max'], 1) }}
                                        </div>
                                    </td>
                                @endforeach
                                <td class="text-center">
                                    <span class="fw-bold fs-6 text-{{ $row['color'] }}">
                                        {{ number_format($row['total'], 1) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $row['color'] }}">{{ $row['letter'] }}</span>
                                    <div class="text-muted" style="font-size:0.7rem;">{{ $row['letter_ar'] }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + $course->gradeCategories->count() }}" class="text-center text-muted py-4">
                                    لا يوجد طلاب مسجلون.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($report->isNotEmpty())
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="2">المتوسط</td>
                                @foreach($course->gradeCategories as $cat)
                                    <td class="text-center">
                                        {{ number_format($report->avg(fn($r) => collect($r['categories'])->firstWhere('name', $cat->name)['contribution'] ?? 0), 1) }}
                                    </td>
                                @endforeach
                                <td class="text-center text-primary">{{ number_format($avg, 1) }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ \App\Models\GradeCategory::gradeColor($avg) }}">
                                        {{ \App\Models\GradeCategory::letterGrade($avg) }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
