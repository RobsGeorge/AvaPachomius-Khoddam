@extends('layouts.app')

@section('title', __('projects.manage_title'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('projects.manage_title') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">{{ __('projects.list') }}</a>
            <a href="{{ route('projects.change-requests.index') }}" class="btn btn-outline-primary">{{ __('projects.change_requests') }}</a>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 fw-bold mb-3">{{ __('projects.create') }}</h2>
            <form method="POST" action="{{ route('projects.assessments.store') }}">
                @csrf
                @if(! $course)
                    <div class="mb-3">
                        <label class="form-label" for="course_id">{{ __('pages.course') }}</label>
                        <input type="number" name="course_id" id="course_id" class="form-control" value="{{ old('course_id') }}" required>
                    </div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="module_id">{{ __('projects.module') }}</label>
                    <select name="module_id" id="module_id" class="form-select @error('module_id') is-invalid @enderror" required>
                        <option value="">—</option>
                        @foreach($modules as $module)
                            <option value="{{ $module->module_id }}" @selected(old('module_id') == $module->module_id)>{{ $module->title }}</option>
                        @endforeach
                    </select>
                    @error('module_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="title">{{ __('projects.assessment') }}</label>
                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">{{ __('projects.description') }}</label>
                    <textarea name="description" id="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="min_team_size">{{ __('projects.min_team') }}</label>
                        <input type="number" name="min_team_size" id="min_team_size" class="form-control" min="1" max="50" value="{{ old('min_team_size', 2) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="max_team_size">{{ __('projects.max_team') }}</label>
                        <input type="number" name="max_team_size" id="max_team_size" class="form-control" min="1" max="50" value="{{ old('max_team_size', 4) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="max_points">{{ __('projects.max_points') }}</label>
                        <input type="number" name="max_points" id="max_points" class="form-control" min="0.01" max="9999.99" step="0.01" value="{{ old('max_points', 100) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="passing_percent">{{ __('projects.passing_percent') }}</label>
                        <input type="number" name="passing_percent" id="passing_percent" class="form-control" min="0" max="100" value="{{ old('passing_percent', 50) }}" required>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-2">{{ __('projects.criteria') }}</div>
                    <p class="small text-muted">{{ __('projects.criteria_hint') }}</p>
                    <div id="criteria-wrap">
                        <div class="row g-2 mb-2">
                            <div class="col-md-8"><input type="text" name="criteria[0][title]" class="form-control" placeholder="{{ __('projects.criterion_title') }}"></div>
                            <div class="col-md-4"><input type="number" name="criteria[0][max_points]" class="form-control" min="0.01" step="0.01" placeholder="{{ __('projects.criterion_max') }}"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat-criteria>{{ __('projects.add_criterion') }}</button>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-2">{{ __('projects.subprojects') }}</div>
                    <p class="small text-muted">{{ __('projects.subprojects_hint') }}</p>
                    @error('title')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    <div id="subprojects-wrap">
                        @php $oldSubs = old('subprojects', [['title' => ''], ['title' => '']]); @endphp
                        @foreach($oldSubs as $index => $row)
                            <div class="row g-2 mb-2">
                                <div class="col-md-5">
                                    <input type="text" name="subprojects[{{ $index }}][title]" class="form-control" value="{{ $row['title'] ?? '' }}" placeholder="{{ __('projects.subproject_title') }}">
                                </div>
                                <div class="col-md-7">
                                    <input type="text" name="subprojects[{{ $index }}][requirements]" class="form-control" value="{{ $row['requirements'] ?? '' }}" placeholder="{{ __('projects.subproject_requirements') }}">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat-subprojects>{{ __('projects.add_subproject') }}</button>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="requirements">{{ __('projects.shared_requirements') }}</label>
                    <textarea name="requirements" id="requirements" class="form-control" rows="3">{{ old('requirements') }}</textarea>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-2">{{ __('projects.phases') }}</div>
                    <div id="phases-wrap">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4"><input type="text" name="phases[0][title]" class="form-control" placeholder="{{ __('projects.phase') }}"></div>
                            <div class="col-md-4"><input type="datetime-local" name="phases[0][deadline]" class="form-control"></div>
                            <div class="col-md-4"><input type="text" name="phases[0][description]" class="form-control" placeholder="{{ __('projects.description') }}"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat="phases">{{ __('projects.add_phase') }}</button>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-2">{{ __('projects.deliverables') }}</div>
                    <div id="deliverables-wrap">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4"><input type="text" name="deliverables[0][title]" class="form-control" placeholder="{{ __('projects.deliverable') }}"></div>
                            <div class="col-md-4"><input type="datetime-local" name="deliverables[0][due_at]" class="form-control"></div>
                            <div class="col-md-4"><input type="text" name="deliverables[0][description]" class="form-control" placeholder="{{ __('projects.description') }}"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat="deliverables">{{ __('projects.add_deliverable') }}</button>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('projects.create') }}</button>
            </form>
        </div>
    </div>

    @forelse($assessments as $assessment)
        <div class="app-card card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">{{ $assessment->title }}</h2>
                        <div class="small text-muted">{{ __('projects.module') }}: {{ $assessment->module->title ?? '—' }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge {{ $assessment->is_published ? 'bg-success' : 'bg-secondary' }}">
                            {{ $assessment->is_published ? __('projects.published_badge') : __('projects.draft_badge') }}
                        </span>
                        @if($assessment->changeRequests->isNotEmpty())
                            <span class="badge bg-warning text-dark">{{ __('projects.pending_changes', ['count' => $assessment->changeRequests->count()]) }}</span>
                        @endif
                    </div>
                </div>

                <p class="small">
                    {{ __('projects.min_team') }}: {{ $assessment->min_team_size }}
                    · {{ __('projects.max_team') }}: {{ $assessment->max_team_size }}
                    · {{ __('projects.max_points') }}: {{ number_format((float) $assessment->max_points, 1) }}
                    · {{ __('projects.passing_percent') }}: {{ (int) $assessment->passing_percent }}%
                </p>

                @foreach($assessment->projects as $project)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <div class="small text-muted">{{ __('projects.subproject') }}</div>
                                <a href="{{ route('projects.show', $project) }}" class="fw-semibold">{{ $project->title }}</a>
                                <div class="small text-muted">
                                    {{ __('projects.fill') }}:
                                    {{ __('projects.seats_of', [
                                        'current' => $project->activeMemberships->count(),
                                        'max' => $assessment->max_team_size,
                                    ]) }}
                                </div>
                                <form method="POST" action="{{ route('projects.update', $project) }}" class="row g-2 align-items-end mt-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-8">
                                        <input type="text" name="title" class="form-control form-control-sm" value="{{ $project->title }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-sm btn-outline-secondary">{{ __('projects.rename_subproject') }}</button>
                                    </div>
                                </form>
                            </div>
                            <span class="badge {{ $project->isClosed() ? 'bg-success' : 'bg-info text-dark' }}">
                                {{ $project->isClosed() ? __('projects.team_closed') : __('projects.team_open') }}
                            </span>
                        </div>
                    </div>
                @endforeach

                <div class="d-flex flex-wrap gap-2 border-top pt-3">
                    <a href="{{ route('projects.grades', $assessment) }}" class="btn btn-sm btn-outline-primary">{{ __('projects.grades') }}</a>
                    <form method="POST" action="{{ route('projects.assessments.publish', $assessment) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-success">{{ $assessment->is_published ? __('projects.unpublish') : __('projects.publish') }}</button>
                    </form>
                    @if($assessment->memberships()->doesntExist())
                        <form method="POST" action="{{ route('projects.assessments.destroy', $assessment) }}" onsubmit="return confirm(@json(__('projects.delete')));">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('projects.delete') }}</button>
                        </form>
                    @endif
                </div>

                <form method="POST" action="{{ route('projects.store', $assessment) }}" class="mt-3">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md-7">
                            <label class="form-label">{{ __('projects.add_subproject') }}</label>
                            <input type="text" name="title" class="form-control" required placeholder="{{ __('projects.subproject_title') }}">
                        </div>
                        <div class="col-md-5">
                            <button class="btn btn-outline-primary">{{ __('projects.add_subproject') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <p class="text-muted">{{ __('projects.empty_manage') }}</p>
    @endforelse
</div>

<script>
document.querySelectorAll('[data-repeat]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-repeat');
        var wrap = document.getElementById(key + '-wrap');
        var index = wrap.children.length;
        var second = key === 'phases' ? 'deadline' : 'due_at';
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2';
        row.innerHTML =
            '<div class="col-md-4"><input type="text" name="' + key + '[' + index + '][title]" class="form-control"></div>' +
            '<div class="col-md-4"><input type="datetime-local" name="' + key + '[' + index + '][' + second + ']" class="form-control"></div>' +
            '<div class="col-md-4"><input type="text" name="' + key + '[' + index + '][description]" class="form-control"></div>';
        wrap.appendChild(row);
    });
});
document.querySelectorAll('[data-repeat-subprojects]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = document.getElementById('subprojects-wrap');
        var index = wrap.children.length;
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2';
        row.innerHTML =
            '<div class="col-md-5"><input type="text" name="subprojects[' + index + '][title]" class="form-control"></div>' +
            '<div class="col-md-7"><input type="text" name="subprojects[' + index + '][requirements]" class="form-control"></div>';
        wrap.appendChild(row);
    });
});
document.querySelectorAll('[data-repeat-criteria]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = document.getElementById('criteria-wrap');
        var index = wrap.children.length;
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2';
        row.innerHTML =
            '<div class="col-md-8"><input type="text" name="criteria[' + index + '][title]" class="form-control"></div>' +
            '<div class="col-md-4"><input type="number" name="criteria[' + index + '][max_points]" class="form-control" min="0.01" step="0.01"></div>';
        wrap.appendChild(row);
    });
});
</script>
@endsection
