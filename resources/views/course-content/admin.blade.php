@extends('layouts.app')

@section('content')
<div class="container py-4">

    {{-- Page header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">إدارة محتوى الدورة</h1>
            <small class="text-muted fw-semibold">{{ $course->title }} — {{ $course->year }}</small>
        </div>
        <a href="{{ route('course-content.show', $course->course_id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye"></i> معاينة كما يراها الطالب
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @forelse($course->modules as $module)
        <div class="card shadow-sm mb-5">
            {{-- Module header --}}
            <div class="card-header d-flex justify-content-between align-items-center py-3"
                 style="background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;">
                <span class="fw-bold fs-5">
                    <i class="bi bi-collection-fill me-2"></i>{{ $module->title }}
                </span>
                <span class="badge bg-white text-dark">{{ $module->lectures->count() }} محاضرة</span>
            </div>

            {{-- Existing lectures --}}
            @if($module->lectures->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="width:80px;">الأسبوع</th>
                                <th style="width:110px;">التاريخ</th>
                                <th>المحاضرة</th>
                                <th style="width:70px;">فيديو</th>
                                <th style="width:70px;">شرائح</th>
                                <th>مواد إضافية</th>
                                <th style="width:110px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($module->lectures as $i => $lecture)
                                <tr>
                                    <td class="text-muted small">{{ $i + 1 }}</td>
                                    <td><span class="badge bg-secondary">{{ $lecture->week_number }}</span></td>
                                    <td class="small text-muted">
                                        {{ $lecture->lecture_date ? $lecture->lecture_date->format('Y-m-d') : '—' }}
                                    </td>
                                    <td class="fw-semibold">
                                        {{ $lecture->title }}
                                        @if($lecture->notes)
                                            <i class="bi bi-sticky text-warning ms-1" title="{{ $lecture->notes }}"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($lecture->video_link)
                                            <a href="{{ $lecture->video_link }}" target="_blank"
                                               class="btn btn-sm btn-outline-danger py-0 px-1">
                                                <i class="bi bi-play-fill"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($lecture->slides_link)
                                            <a href="{{ $lecture->slides_link }}" target="_blank"
                                               class="btn btn-sm btn-outline-primary py-0 px-1">
                                                <i class="bi bi-file-earmark-slides"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-muted small">{{ $lecture->materials->count() }} رابط</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('lectures.edit', $lecture->lecture_id) }}"
                                               class="btn btn-sm btn-outline-primary py-0 px-2" title="تعديل">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST"
                                                  action="{{ route('lectures.destroy', $lecture->lecture_id) }}"
                                                  onsubmit="return confirm('حذف هذه المحاضرة؟')">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="course_id" value="{{ $course->course_id }}">
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2" title="حذف">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Add lecture form --}}
            <div class="card-footer bg-light">
                <div class="fw-semibold mb-2 text-muted small">
                    <i class="bi bi-plus-circle-fill text-success"></i> إضافة محاضرة جديدة
                </div>
                <form method="POST" action="{{ route('lectures.store') }}">
                    @csrf
                    <input type="hidden" name="module_id" value="{{ $module->module_id }}">
                    <input type="hidden" name="course_id"  value="{{ $course->course_id }}">

                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <input type="text" name="title" class="form-control form-control-sm"
                                   placeholder="عنوان المحاضرة *" maxlength="150" required>
                        </div>
                        <div class="col-md-1">
                            <input type="number" name="week_number" class="form-control form-control-sm"
                                   placeholder="أسبوع *" min="1" max="99" required>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="lecture_date" class="form-control form-control-sm"
                                   placeholder="التاريخ">
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="order_index" class="form-control form-control-sm"
                                   placeholder="الترتيب" min="0" value="0">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <input type="url" name="video_link" class="form-control form-control-sm"
                                   placeholder="رابط الفيديو (اختياري)" maxlength="500">
                        </div>
                        <div class="col-md-4">
                            <input type="url" name="slides_link" class="form-control form-control-sm"
                                   placeholder="رابط الشرائح / PDF (اختياري)" maxlength="500">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success btn-sm w-100">
                                <i class="bi bi-plus-circle"></i> إضافة
                            </button>
                        </div>
                    </div>
                    <textarea name="notes" class="form-control form-control-sm"
                              rows="2" placeholder="ملاحظات (اختياري)"></textarea>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-info">
            لا توجد وحدات مضافة لهذه الدورة بعد.
            أضف وحدات أولاً من صفحة إدارة الوحدات.
        </div>
    @endforelse

</div>
@endsection
