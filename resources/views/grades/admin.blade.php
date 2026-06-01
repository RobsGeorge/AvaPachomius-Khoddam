@extends('layouts.app')

@section('content')
<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="mb-0">إدارة التقييم</h1>
            <small class="text-muted fw-semibold">{{ $course->title }} — {{ $course->year }}</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('grades.report', $course->course_id) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-table"></i> تقرير الدرجات
            </a>
            <a href="{{ route('course-content.admin', $course->course_id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-collection"></i> المحتوى
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Weight summary bar --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold">إجمالي أوزان التقييم</span>
                <span class="fw-bold {{ $totalWeight > 100 ? 'text-danger' : ($totalWeight == 100 ? 'text-success' : 'text-warning') }}">
                    {{ number_format($totalWeight, 1) }} / 100%
                </span>
            </div>
            <div class="progress" style="height:8px;">
                <div class="progress-bar {{ $totalWeight > 100 ? 'bg-danger' : ($totalWeight == 100 ? 'bg-success' : 'bg-warning') }}"
                     style="width:{{ min($totalWeight, 100) }}%"></div>
            </div>
            @if($totalWeight != 100)
                <small class="text-{{ $totalWeight > 100 ? 'danger' : 'warning' }} mt-1 d-block">
                    {{ $totalWeight > 100 ? 'تجاوز الوزن 100%، يرجى المراجعة.' : 'الوزن الإجمالي لم يصل بعد إلى 100%.' }}
                </small>
            @endif
        </div>
    </div>

    {{-- Add category form --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-plus-circle text-primary"></i> إضافة فئة تقييم جديدة</div>
        <div class="card-body">
            <form method="POST" action="{{ route('grade-categories.store', $course->course_id) }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">النوع</label>
                        <select name="type" class="form-select form-select-sm" required>
                            @foreach(\App\Models\GradeCategory::$types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">الاسم المخصص</label>
                        <input type="text" name="name" class="form-control form-control-sm"
                               placeholder="مثال: الاختبار النهائي" maxlength="100" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">الوزن %</label>
                        <input type="number" name="weight_percentage" class="form-control form-control-sm"
                               placeholder="30" min="0" max="100" step="0.5" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small fw-semibold">الترتيب</label>
                        <input type="number" name="ordering" class="form-control form-control-sm"
                               value="{{ $course->gradeCategories->count() }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-plus-circle"></i> إضافة
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Categories --}}
    @forelse($course->gradeCategories as $cat)
        @php
            $color = \App\Models\GradeCategory::$typeColors[$cat->type] ?? 'secondary';
            $icon  = \App\Models\GradeCategory::$typeIcons[$cat->type]  ?? 'bi-three-dots';
        @endphp
        <div class="card shadow-sm mb-4 border-{{ $color }}">
            {{-- Category header --}}
            <div class="card-header d-flex justify-content-between align-items-center bg-{{ $color }} bg-opacity-10">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi {{ $icon }} text-{{ $color }} fs-5"></i>
                    <span class="fw-bold">{{ $cat->name }}</span>
                    <span class="badge bg-{{ $color }}">{{ number_format($cat->weight_percentage, 1) }}%</span>
                    <span class="text-muted small">{{ \App\Models\GradeCategory::$types[$cat->type] ?? '' }}</span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    {{-- Inline edit form --}}
                    <form method="POST" action="{{ route('grade-categories.update', $cat->category_id) }}"
                          class="d-flex gap-1 align-items-center">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $cat->name }}"
                               class="form-control form-control-sm" style="width:160px;" maxlength="100" required>
                        <input type="number" name="weight_percentage" value="{{ $cat->weight_percentage }}"
                               class="form-control form-control-sm" style="width:70px;" min="0" max="100" step="0.5" required>
                        <span class="text-muted small">%</span>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" title="حفظ">
                            <i class="bi bi-check"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('grade-categories.destroy', $cat->category_id) }}"
                          onsubmit="return confirm('حذف هذه الفئة وجميع بنودها ودرجاتها؟')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger py-0 px-2" title="حذف">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Items table --}}
            @if($cat->items->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>البند</th>
                                <th>الدرجة القصوى</th>
                                <th>التاريخ</th>
                                <th>الوصف</th>
                                <th>مُصحَّح</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cat->items as $i => $item)
                                <tr>
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td class="fw-semibold">{{ $item->title }}</td>
                                    <td>{{ number_format($item->max_score, 1) }}</td>
                                    <td>{{ $item->item_date ? $item->item_date->format('Y-m-d') : '—' }}</td>
                                    <td class="text-muted">{{ Str::limit($item->description, 50) }}</td>
                                    <td>
                                        @php $graded = $item->gradedStudentsCount(); @endphp
                                        <span class="badge {{ $graded == $studentCount && $studentCount > 0 ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $graded }} / {{ $studentCount }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('grade-items.scores', $item->item_id) }}"
                                               class="btn btn-sm btn-outline-success py-0 px-2" title="إدخال الدرجات">
                                                <i class="bi bi-input-cursor-text"></i> درجات
                                            </a>
                                            <form method="POST" action="{{ route('grade-items.destroy', $item->item_id) }}"
                                                  onsubmit="return confirm('حذف هذا البند وجميع درجاته؟')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="2" class="fw-semibold text-muted">المجموع</td>
                                <td class="fw-semibold">{{ number_format($cat->items->sum('max_score'), 1) }}</td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            {{-- Add item form --}}
            <div class="card-footer bg-light">
                <div class="small text-muted fw-semibold mb-2"><i class="bi bi-plus text-success"></i> إضافة بند جديد</div>
                <form method="POST" action="{{ route('grade-items.store', $cat->category_id) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="title" class="form-control form-control-sm"
                                   placeholder="اسم البند *" maxlength="150" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="max_score" class="form-control form-control-sm"
                                   placeholder="الدرجة القصوى *" min="0.01" step="0.5" required>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="item_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="description" class="form-control form-control-sm"
                                   placeholder="وصف (اختياري)" maxlength="255">
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-success btn-sm w-100">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-info">
            لا توجد فئات تقييم بعد. أضف أولى الفئات أعلاه.
        </div>
    @endforelse

</div>
@endsection
