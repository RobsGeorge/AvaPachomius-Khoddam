@php
    $colspan = ($requiresChurch ?? false) ? 4 : 5;
    $isEditing = (string) old('edit_course_id') === (string) $course->course_id;
@endphp
<tr>
    @unless($requiresChurch)
        <td class="text-muted-theme small">
            {{ $course->church?->name ?? $course->service?->church?->name ?? '—' }}
        </td>
    @endunless
    <td @if($requiresChurch) class="ps-5" @endif>
        <div class="fw-semibold">{{ $course->localizedTitle() }}</div>
        <div class="text-muted-theme small text-truncate" style="max-width:240px;" title="{{ $course->description }}">
            {{ $course->description }}
        </div>
    </td>
    <td>{{ $course->service?->localizedTitle() ?? '—' }}</td>
    <td>{{ $course->year }}</td>
    <td>
        <div class="d-flex gap-1 justify-content-end">
            <button type="button"
                    class="btn btn-xs btn-outline-primary py-0 px-1"
                    data-bs-toggle="collapse"
                    data-bs-target="#edit-course-{{ $course->course_id }}"
                    aria-expanded="{{ $isEditing ? 'true' : 'false' }}"
                    aria-controls="edit-course-{{ $course->course_id }}"
                    title="{{ __('pages.edit_course') }}">
                <i class="bi bi-pencil"></i>
            </button>
            <form method="POST"
                  action="{{ route('superadmin.courses.destroy', $course->course_id) }}"
                  data-confirm="{{ __('pages.confirm_delete_course') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1" title="{{ __('pages.delete') }}">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </td>
</tr>
<tr class="collapse @if($isEditing) show @endif" id="edit-course-{{ $course->course_id }}">
    <td colspan="{{ $colspan }}" class="bg-light border-top-0 pt-0">
        <form method="POST" action="{{ route('superadmin.courses.update', $course->course_id) }}" class="p-3 border rounded bg-white">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_course_id" value="{{ $course->course_id }}">
            <div class="small fw-semibold text-muted-theme mb-2">
                <i class="bi bi-pencil-square"></i> {{ __('pages.edit_course') }}
            </div>
            <div class="row g-2">
                @if($requiresChurch ?? false)
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">{{ __('tenancy.col_name') }}</label>
                        <select name="church_id" class="form-select form-select-sm @if($isEditing && $errors->has('church_id')) is-invalid @endif" required>
                            <option value="">{{ __('service.choose_church') }}</option>
                            @foreach($churches ?? [] as $church)
                                <option value="{{ $church->church_id }}"
                                        @selected((string) ($isEditing ? old('church_id', $course->church_id ?? $course->service?->church_id) : ($course->church_id ?? $course->service?->church_id)) === (string) $church->church_id)>
                                    {{ $church->name }}
                                </option>
                            @endforeach
                        </select>
                        @if($isEditing)@error('church_id')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                    </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">{{ __('pages.course_title') }}</label>
                    <input type="text" name="title"
                           class="form-control form-control-sm @if($isEditing && $errors->has('title')) is-invalid @endif"
                           value="{{ $isEditing ? old('title', $course->title) : $course->title }}" maxlength="30" required>
                    @if($isEditing)@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">{{ __('course_context.title_ar') }}</label>
                    <input type="text" name="title_ar"
                           class="form-control form-control-sm @if($isEditing && $errors->has('title_ar')) is-invalid @endif"
                           value="{{ $isEditing ? old('title_ar', $course->title_ar) : $course->title_ar }}" maxlength="120">
                    @if($isEditing)@error('title_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">{{ __('course_context.title_en') }}</label>
                    <input type="text" name="title_en"
                           class="form-control form-control-sm @if($isEditing && $errors->has('title_en')) is-invalid @endif"
                           value="{{ $isEditing ? old('title_en', $course->title_en) : $course->title_en }}" maxlength="120">
                    @if($isEditing)@error('title_en')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">{{ __('pages.year') }}</label>
                    <input type="number" name="year"
                           class="form-control form-control-sm @if($isEditing && $errors->has('year')) is-invalid @endif"
                           value="{{ $isEditing ? old('year', $course->year) : $course->year }}" min="2000" max="2100" required>
                    @if($isEditing)@error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">{{ __('pages.default_session_start_time') }}</label>
                    <input type="time" name="default_session_start_time"
                           class="form-control form-control-sm @if($isEditing && $errors->has('default_session_start_time')) is-invalid @endif"
                           value="{{ $isEditing ? old('default_session_start_time', $course->formattedDefaultSessionStartTime()) : $course->formattedDefaultSessionStartTime() }}" required>
                    @if($isEditing)@error('default_session_start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold mb-1">{{ __('pages.description') }}</label>
                    <textarea name="description" rows="2"
                              class="form-control form-control-sm @if($isEditing && $errors->has('description')) is-invalid @endif"
                              maxlength="255" required>{{ $isEditing ? old('description', $course->description) : $course->description }}</textarea>
                    @if($isEditing)@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">{{ __('course_context.description_ar') }}</label>
                    <textarea name="description_ar" rows="2"
                              class="form-control form-control-sm @if($isEditing && $errors->has('description_ar')) is-invalid @endif">{{ $isEditing ? old('description_ar', $course->description_ar) : $course->description_ar }}</textarea>
                    @if($isEditing)@error('description_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">{{ __('course_context.description_en') }}</label>
                    <textarea name="description_en" rows="2"
                              class="form-control form-control-sm @if($isEditing && $errors->has('description_en')) is-invalid @endif">{{ $isEditing ? old('description_en', $course->description_en) : $course->description_en }}</textarea>
                    @if($isEditing)@error('description_en')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                </div>
                @if(($services ?? collect())->isNotEmpty())
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">{{ __('service.label') }}</label>
                        <select name="service_id" class="form-select form-select-sm @if($isEditing && $errors->has('service_id')) is-invalid @endif" required>
                            <option value="">{{ __('service.choose_service') }}</option>
                            @foreach($services as $service)
                                <option value="{{ $service->service_id }}"
                                        data-church-id="{{ $service->church_id }}"
                                        @selected((string) ($isEditing ? old('service_id', $course->service_id) : $course->service_id) === (string) $service->service_id)>
                                    @if($requiresChurch ?? false)
                                        {{ $service->church?->name }} — {{ $service->localizedTitle() }}
                                    @else
                                        {{ $service->localizedTitle() }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @if($isEditing)@error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif
                    </div>
                @endif
                <div class="col-12 d-flex gap-2 justify-content-end">
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="collapse"
                            data-bs-target="#edit-course-{{ $course->course_id }}">
                        {{ __('pages.cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-save"></i> {{ __('pages.save') }}
                    </button>
                </div>
            </div>
        </form>
    </td>
</tr>
