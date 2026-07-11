@extends('layouts.app')

@section('title', __('course_applications.builder_index_title'))

@section('content')
<div class="container-fluid py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('course_applications.builder_index_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('course_applications.builder_index_intro') }}</p>
        </div>
        <a href="{{ route('admin.course-applications.index') }}" class="btn btn-outline-secondary btn-sm">
            {{ __('course_applications.queue_title') }}
        </a>
    </div>

    <div class="table-responsive app-card card shadow-sm">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('pages.course_title') }}</th>
                    <th>{{ __('pages.year') }}</th>
                    <th>{{ __('course_applications.enable_form') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($courses as $course)
                    @php $form = $forms[$course->course_id] ?? null; @endphp
                    <tr>
                        <td class="fw-semibold">{{ $course->title }}</td>
                        <td>{{ $course->year }}</td>
                        <td>
                            @if($form?->is_enabled)
                                <span class="badge bg-success">{{ __('course_applications.yes') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('course_applications.no') }}</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.courses.application-form.preview', $course->course_id) }}" class="btn btn-sm btn-outline-secondary">
                                {{ __('course_applications.applicant_view') }}
                            </a>
                            <a href="{{ route('admin.courses.application-form.edit', $course->course_id) }}" class="btn btn-sm btn-primary">
                                {{ __('course_applications.manage_form') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted-theme py-4">{{ __('pages.no_records') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
