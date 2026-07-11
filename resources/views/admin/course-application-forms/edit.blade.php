@extends('layouts.app')

@section('title', __('course_applications.form_title'))

@section('content')
@php
    use App\Models\CourseApplicationFormField;
@endphp
<div class="container-fluid py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('course_applications.form_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ $courseModel->title }} — {{ __('course_applications.form_intro') }}</p>
        </div>
        <a href="{{ route('curriculum.admin', $courseModel->course_id) }}" class="btn btn-outline-secondary btn-sm">
            {{ __('pages.back') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.courses.application-form.update', $courseModel->course_id) }}">
                @csrf
                @method('PUT')
                <div class="row g-3 align-items-end">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" value="1"
                                   @checked(old('is_enabled', $form->is_enabled))>
                            <label class="form-check-label" for="is_enabled">{{ __('course_applications.enable_form') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="title" class="form-label">{{ __('pages.course_title') }}</label>
                        <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $form->title) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="default_role_id" class="form-label">{{ __('course_applications.default_role') }}</label>
                        <select name="default_role_id" id="default_role_id" class="form-select">
                            <option value="">{{ __('pages.select_role') }}</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->role_id }}" @selected(old('default_role_id', $form->default_role_id) == $role->role_id)>
                                    {{ $role->role_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="description" class="form-label">{{ __('pages.description') }}</label>
                        <textarea name="description" id="description" rows="2" class="form-control">{{ old('description', $form->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('pages.save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">{{ __('course_applications.builder_steps') }}</h2>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#add-step-modal">
            {{ __('course_applications.add_step') }}
        </button>
    </div>

    @forelse($form->steps as $step)
        <div class="app-card card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">{{ $step->order_index + 1 }}. {{ $step->title }}</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                            data-bs-target="#edit-step-{{ $step->id }}">{{ __('pages.edit') }}</button>
                    <form method="POST" action="{{ route('admin.courses.application-form.steps.destroy', [$courseModel->course_id, $step]) }}"
                          data-confirm="{{ __('pages.confirm_delete') }}"
                          onsubmit="return confirm(this.dataset.confirm);">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('pages.delete') }}</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                @if($step->description)
                    <p class="text-muted-theme small">{{ $step->description }}</p>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-3">
                        <thead>
                            <tr>
                                <th>{{ __('course_applications.field_label') }}</th>
                                <th>{{ __('course_applications.field_type') }}</th>
                                <th>{{ __('course_applications.field_required') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($step->fields as $field)
                                <tr>
                                    <td>{{ $field->label }}</td>
                                    <td>{{ __('course_applications.field_types.'.$field->type) }}</td>
                                    <td>{{ $field->required ? __('course_applications.yes') : __('course_applications.no') }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#edit-field-{{ $field->id }}">{{ __('pages.edit') }}</button>
                                        <form method="POST" class="d-inline"
                                              action="{{ route('admin.courses.application-form.fields.destroy', [$courseModel->course_id, $field]) }}"
                                              data-confirm="{{ __('pages.confirm_delete') }}"
                                              onsubmit="return confirm(this.dataset.confirm);">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('pages.delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted-theme text-center">{{ __('pages.no_records') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#add-field-{{ $step->id }}">
                    {{ __('course_applications.add_field') }}
                </button>
            </div>
        </div>

        @include('admin.course-application-forms.partials.step-modals', ['step' => $step, 'courseModel' => $courseModel])
        @include('admin.course-application-forms.partials.field-modals', ['step' => $step, 'fieldTypes' => $fieldTypes, 'courseModel' => $courseModel])
    @empty
        <div class="alert alert-info">{{ __('pages.no_records') }}</div>
    @endforelse
</div>

<div class="modal fade" id="add-step-modal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.courses.application-form.steps.store', $courseModel->course_id) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('course_applications.add_step') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.title') }}</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.description') }}</label>
                    <textarea name="description" rows="2" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
