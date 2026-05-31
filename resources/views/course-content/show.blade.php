@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">{{ $course->title }}</h1>
            <small class="text-muted">{{ $course->description }} &mdash; {{ $course->year }}</small>
        </div>
        @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
            <a href="{{ route('course-content.admin', $course->course_id) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil-square"></i> إدارة المحتوى
            </a>
        @endif
    </div>

    @forelse($course->modules as $module)
        <div class="card shadow-sm mb-4">
            {{-- Module header --}}
            <div class="card-header d-flex justify-content-between align-items-center"
                 style="background: linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff;">
                <span class="fw-bold fs-5">
                    <i class="bi bi-collection-fill me-2"></i>{{ $module->title }}
                </span>
                <span class="badge bg-white text-dark">
                    {{ $module->lectures->count() }} محاضرة
                </span>
            </div>

            @if($module->description)
                <div class="px-3 pt-2 text-muted small">{{ $module->description }}</div>
            @endif

            <div class="card-body p-0">
                @if($module->lectures->isEmpty())
                    <p class="text-center text-muted py-4 mb-0">لا توجد محاضرات بعد لهذا الوحدة.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:50px;">#</th>
                                    <th class="text-center" style="width:80px;">الأسبوع</th>
                                    <th style="width:110px;">التاريخ</th>
                                    <th>المحاضرة</th>
                                    <th class="text-center" style="width:80px;">الفيديو</th>
                                    <th class="text-center" style="width:80px;">الشرائح</th>
                                    <th>المواد الإضافية</th>
                                    <th>الملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($module->lectures as $i => $lecture)
                                    <tr>
                                        <td class="text-center text-muted small">{{ $i + 1 }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary rounded-pill">
                                                أسبوع {{ $lecture->week_number }}
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            {{ $lecture->lecture_date
                                                ? $lecture->lecture_date->format('Y-m-d')
                                                : '—' }}
                                        </td>
                                        <td class="fw-semibold">{{ $lecture->title }}</td>
                                        <td class="text-center">
                                            @if($lecture->video_link)
                                                <a href="{{ $lecture->video_link }}" target="_blank"
                                                   class="btn btn-sm btn-danger rounded-circle p-1 lh-1"
                                                   title="مشاهدة الفيديو">
                                                    <i class="bi bi-play-fill"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($lecture->slides_link)
                                                <a href="{{ $lecture->slides_link }}" target="_blank"
                                                   class="btn btn-sm btn-primary rounded-circle p-1 lh-1"
                                                   title="تنزيل الشرائح">
                                                    <i class="bi bi-file-earmark-slides-fill"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @forelse($lecture->materials as $mat)
                                                <a href="{{ $mat->link }}" target="_blank"
                                                   class="badge bg-light text-primary border text-decoration-none me-1 mb-1 d-inline-block"
                                                   title="{{ $mat->link }}">
                                                    <i class="bi bi-link-45deg"></i> {{ $mat->title }}
                                                </a>
                                            @empty
                                                <span class="text-muted small">—</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            @if($lecture->notes)
                                                <span class="text-muted small" style="white-space:pre-line;">
                                                    {{ Str::limit($lecture->notes, 80) }}
                                                </span>
                                                @if(strlen($lecture->notes) > 80)
                                                    <a href="#" data-bs-toggle="modal"
                                                       data-bs-target="#notes-{{ $lecture->lecture_id }}"
                                                       class="small">المزيد</a>
                                                    <div class="modal fade" id="notes-{{ $lecture->lecture_id }}" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">{{ $lecture->title }}</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body" style="white-space:pre-line;">{{ $lecture->notes }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="alert alert-info">لا توجد وحدات مضافة لهذه الدورة بعد.</div>
    @endforelse

</div>
@endsection
