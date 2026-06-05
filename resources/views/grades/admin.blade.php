@extends('layouts.app')

@section('title', __('pages.grading_management'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-0">{{ __('pages.grading_management') }}</h1>
            <small class="text-muted-theme fw-semibold">{{ $course->title }} — {{ $course->year }}</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('graduation.show', $course->course_id) }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-mortarboard"></i> {{ __('pages.graduation_title') }}
            </a>
            <a href="{{ route('grades.report', $course->course_id) }}" class="btn btn-outline-theme btn-sm">
                <i class="bi bi-table"></i> {{ __('pages.grade_report') }}
            </a>
            <a href="{{ route('course-content.admin', $course->course_id) }}" class="btn btn-outline-theme btn-sm">
                <i class="bi bi-collection"></i> {{ __('pages.content') }}
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

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold">{{ __('pages.total_grading_weights') }}</span>
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
                    {{ $totalWeight > 100 ? __('pages.weight_over_100') : __('pages.weight_under_100') }}
                </small>
            @endif
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-plus-circle text-primary"></i> {{ __('pages.add_grade_category') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('grade-categories.store', $course->course_id) }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">{{ __('pages.type') }}</label>
                        <select name="type" class="form-select form-select-sm" required>
                            @foreach(\App\Models\GradeCategory::$types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">{{ __('pages.custom_name') }}</label>
                        <input type="text" name="name" class="form-control form-control-sm"
                               placeholder="{{ __('pages.example_final_exam') }}" maxlength="100" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">{{ __('pages.weight_percent') }}</label>
                        <input type="number" name="weight_percentage" class="form-control form-control-sm"
                               placeholder="30" min="0" max="100" step="0.5" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small fw-semibold">{{ __('pages.order') }}</label>
                        <input type="number" name="ordering" class="form-control form-control-sm"
                               value="{{ $course->gradeCategories->count() }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-plus-circle"></i> {{ __('pages.add') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @forelse($course->gradeCategories as $cat)
        @php
            $color = \App\Models\GradeCategory::$typeColors[$cat->type] ?? 'secondary';
            $icon  = \App\Models\GradeCategory::$typeIcons[$cat->type]  ?? 'bi-three-dots';
        @endphp
        <div class="app-card card shadow-sm mb-4 border-{{ $color }}">
            <div class="card-header d-flex justify-content-between align-items-center bg-{{ $color }} bg-opacity-10">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi {{ $icon }} text-{{ $color }} fs-5"></i>
                    <span class="fw-bold">{{ $cat->name }}</span>
                    <span class="badge bg-{{ $color }}">{{ number_format($cat->weight_percentage, 1) }}%</span>
                    <span class="text-muted-theme small">{{ \App\Models\GradeCategory::$types[$cat->type] ?? '' }}</span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <form method="POST" action="{{ route('grade-categories.update', $cat->category_id) }}"
                          class="d-flex gap-1 align-items-center">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $cat->name }}"
                               class="form-control form-control-sm" style="width:160px;" maxlength="100" required>
                        <input type="number" name="weight_percentage" value="{{ $cat->weight_percentage }}"
                               class="form-control form-control-sm" style="width:70px;" min="0" max="100" step="0.5" required>
                        <span class="text-muted-theme small">%</span>
                        <button class="btn btn-sm btn-outline-theme py-0 px-2" title="{{ __('pages.save') }}">
                            <i class="bi bi-check"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('grade-categories.destroy', $cat->category_id) }}"
                          onsubmit="return confirm(@json(__('pages.confirm_delete_category_items')))">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger py-0 px-2" title="{{ __('pages.delete') }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>

            @if($cat->items->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('pages.number') }}</th>
                                <th>{{ __('pages.item_name') }}</th>
                                <th>{{ __('pages.max_score') }}</th>
                                <th>{{ __('pages.date') }}</th>
                                <th>{{ __('pages.description') }}</th>
                                <th>{{ __('pages.corrected') }}</th>
                                <th>{{ __('pages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cat->items as $i => $item)
                                <tr>
                                    <td class="text-muted-theme">{{ $i + 1 }}</td>
                                    <td class="fw-semibold">{{ $item->title }}</td>
                                    <td>{{ number_format($item->max_score, 1) }}</td>
                                    <td>{{ $item->item_date ? $item->item_date->format('Y-m-d') : '—' }}</td>
                                    <td class="text-muted-theme">{{ Str::limit($item->description, 50) }}</td>
                                    <td>
                                        @php $graded = $item->gradedStudentsCount(); @endphp
                                        <span class="badge {{ $graded == $studentCount && $studentCount > 0 ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $graded }} / {{ $studentCount }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('grade-items.scores', $item->item_id) }}"
                                               class="btn btn-sm btn-outline-success py-0 px-2" title="{{ __('pages.enter_scores') }}">
                                                <i class="bi bi-input-cursor-text"></i> {{ __('pages.grades') }}
                                            </a>
                                            <form method="POST" action="{{ route('grade-items.destroy', $item->item_id) }}"
                                                  onsubmit="return confirm(@json(__('pages.confirm_delete_item_grades')))">
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
                                <td colspan="2" class="fw-semibold text-muted-theme">{{ __('pages.total') }}</td>
                                <td class="fw-semibold">{{ number_format($cat->items->sum('max_score'), 1) }}</td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            <div class="card-footer bg-light">
                <div class="small text-muted-theme fw-semibold mb-2"><i class="bi bi-plus text-success"></i> {{ __('pages.add_new_item') }}</div>
                <form method="POST" action="{{ route('grade-items.store', $cat->category_id) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="title" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.item_name_required') }}" maxlength="150" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="max_score" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.max_score') }} *" min="0.01" step="0.5" required>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="item_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="description" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.item_desc_optional') }}" maxlength="255">
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
        <div class="alert alert-info">{{ __('pages.no_grade_categories') }}</div>
    @endforelse
</div>
@endsection
