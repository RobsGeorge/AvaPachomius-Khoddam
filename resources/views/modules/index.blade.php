@extends('layouts.app')

@section('title', __('pages.modules_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.modules_title') }}</h1>
        <a href="{{ route('modules.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> {{ __('pages.create_new_module') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('pages.number') }}</th>
                        <th>{{ __('pages.module_name') }}</th>
                        <th>{{ __('pages.description') }}</th>
                        <th>{{ __('pages.lectures') }}</th>
                        <th>{{ __('pages.exams_count') }}</th>
                        <th>{{ __('pages.linked_courses') }}</th>
                        <th>{{ __('pages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($modules as $module)
                        <tr>
                            <td class="text-muted-theme small">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $module->title }}</td>
                            <td class="text-muted-theme small">{{ $module->description }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $module->lectures_count }}</span>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark">{{ $module->exams_count }}</span>
                            </td>
                            <td>
                                @forelse($module->courses as $course)
                                    <span class="badge bg-light text-dark border me-1">{{ $course->title }}</span>
                                @empty
                                    <span class="text-muted-theme small">—</span>
                                @endforelse
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('modules.edit', $module->module_id) }}"
                                       class="btn btn-sm btn-outline-theme">
                                        <i class="bi bi-pencil"></i> {{ __('pages.edit') }}
                                    </a>
                                    <form method="POST" action="{{ route('modules.destroy', $module->module_id) }}"
                                          onsubmit="return confirm(@json(__('pages.confirm_delete_module_cascade')))">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted-theme py-4">
                                {{ __('pages.no_modules_yet') }}
                                <a href="{{ route('modules.create') }}">{{ __('pages.create_now') }}</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
