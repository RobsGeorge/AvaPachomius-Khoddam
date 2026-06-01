@extends('layouts.app')

@section('content')
<div class="container animate-in py-4">

    {{-- Page header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="mb-0">{{ __('pages.course_content_admin') }}</h1>
            <small class="text-muted fw-semibold">{{ $course->title }} — {{ $course->year }}</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('grades.admin', $course->course_id) }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-bar-chart-line"></i> {{ __('pages.grading_weights') }}
            </a>
            <a href="{{ route('course-content.show', $course->course_id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye"></i> {{ __('pages.student_preview') }}
            </a>
        </div>
    </div>

    {{-- Module management strip --}}
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header fw-semibold text-primary">
            <i class="bi bi-collection"></i> {{ __('pages.manage_modules') }}
        </div>
        <div class="card-body">
            <div class="row g-3">
                {{-- Link existing module --}}
                @if($availableModules->isNotEmpty())
                    <div class="col-md-5">
                        <form method="POST" action="{{ route('course-content.attach-module', $course->course_id) }}" class="d-flex gap-2">
                            @csrf
                            <select name="module_id" class="form-select form-select-sm" required>
                                <option value="">-- {{ __('pages.link_existing_module') }} --</option>
                                @foreach($availableModules as $m)
                                    <option value="{{ $m->module_id }}">{{ $m->title }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-primary text-nowrap">
                                <i class="bi bi-link-45deg"></i> {{ __('pages.link') }}
                            </button>
                        </form>
                    </div>
                    <div class="col-auto d-flex align-items-center text-muted">{{ __('pages.or') }}</div>
                @endif
                {{-- Create new module --}}
                <div class="col">
                    <form method="POST" action="{{ route('course-content.create-attach-module', $course->course_id) }}" class="d-flex gap-2 flex-wrap">
                        @csrf
                        <input type="text" name="title" class="form-control form-control-sm"
                               placeholder="{{ __('pages.new_module_name') }}" maxlength="30" required style="min-width:150px;">
                        <input type="text" name="description" class="form-control form-control-sm"
                               placeholder="{{ __('pages.module_desc_required') }}" maxlength="255" required style="min-width:180px;">
                        <button class="btn btn-sm btn-success text-nowrap">
                            <i class="bi bi-plus-circle"></i> {{ __('pages.create_and_link') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
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
                    @if($module->description)
                        <small class="fw-normal opacity-75 ms-2">{{ $module->description }}</small>
                    @endif
                </span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-white text-dark">{{ $module->lectures->count() }} {{ __('pages.lecture') }}</span>
                    <a href="{{ route('modules.edit', $module->module_id) }}"
                       class="btn btn-sm btn-light py-0 px-2" title="{{ __('pages.edit') }} {{ __('pages.module') }}">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST"
                          action="{{ route('course-content.detach-module', [$course->course_id, $module->module_id]) }}"
                          onsubmit="return confirm(@json(__('pages.unlink_module_confirm')))">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-light py-0 px-2" title="{{ __('pages.unlink_from_course') }}">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Existing lectures --}}
            @if($module->lectures->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="width:80px;">{{ __('pages.week_col') }}</th>
                                <th style="width:110px;">{{ __('pages.date') }}</th>
                                <th>{{ __('pages.lecture') }}</th>
                                <th style="width:70px;">{{ __('pages.video_col') }}</th>
                                <th style="width:70px;">{{ __('pages.slides_col') }}</th>
                                <th>{{ __('pages.extra_materials_col') }}</th>
                                <th style="width:110px;">{{ __('pages.actions') }}</th>
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
                                        <span class="text-muted-theme small">{{ __('pages.links_count', ['count' => $lecture->materials->count()]) }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('lectures.edit', $lecture->lecture_id) }}"
                                               class="btn btn-sm btn-outline-primary py-0 px-2" title="{{ __('pages.edit') }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST"
                                                  action="{{ route('lectures.destroy', $lecture->lecture_id) }}"
                                                  onsubmit="return confirm(@json(__('pages.confirm_delete_lecture')))">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="course_id" value="{{ $course->course_id }}">
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2" title="{{ __('pages.delete') }}">
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
                    <i class="bi bi-plus-circle-fill text-success"></i> {{ __('pages.add_new_lecture') }}
                </div>
                <form method="POST" action="{{ route('lectures.store') }}">
                    @csrf
                    <input type="hidden" name="module_id" value="{{ $module->module_id }}">
                    <input type="hidden" name="course_id"  value="{{ $course->course_id }}">

                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <input type="text" name="title" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.lecture_title_placeholder') }}" maxlength="150" required>
                        </div>
                        <div class="col-md-1">
                            <input type="number" name="week_number" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.week') }} *" min="1" max="99" required>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="lecture_date" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.date') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="order_index" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.sort_order_placeholder') }}" min="0" value="0">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <input type="url" name="video_link" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.video_url_optional') }}" maxlength="500">
                        </div>
                        <div class="col-md-4">
                            <input type="url" name="slides_link" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.slides_url_optional') }}" maxlength="500">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success btn-sm w-100">
                                <i class="bi bi-plus-circle"></i> {{ __('pages.add') }}
                            </button>
                        </div>
                    </div>
                    <textarea name="notes" class="form-control form-control-sm"
                              rows="2" placeholder="{{ __('pages.notes_optional') }}"></textarea>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-info">
            {{ __('pages.no_modules_for_course') }}
            {{ __('pages.add_modules_hint') }}
        </div>
    @endforelse

</div>
@endsection
