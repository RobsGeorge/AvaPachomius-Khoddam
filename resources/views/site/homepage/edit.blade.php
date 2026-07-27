@extends('layouts.app')

@section('title', __('public_site.homepage_edit_title'))

@section('content')
<div class="container py-3" style="max-width:900px;">
    <h1 class="page-title mb-1">{{ __('public_site.homepage_edit_title') }}</h1>
    <p class="text-muted-theme mb-3">{{ __('public_site.homepage_edit_intro') }}</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('site.preview') }}" class="btn btn-outline-secondary btn-sm" target="_blank">
            {{ __('public_site.preview') }}
        </a>
        @can('public_site.publish')
            @if($site->isPublished())
                <form method="post" action="{{ route('site.homepage.unpublish') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm">{{ __('public_site.unpublish') }}</button>
                </form>
            @else
                <form method="post" action="{{ route('site.homepage.publish') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('public_site.publish') }}</button>
                </form>
            @endif
        @endcan
        @if($site->isPublished())
            <span class="badge bg-success align-self-center">{{ __('public_site.status_published') }}</span>
        @else
            <span class="badge bg-secondary align-self-center">{{ __('public_site.status_draft') }}</span>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6">{{ __('public_site.add_section') }}</h2>
            <form method="post" action="{{ route('site.homepage.sections.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-8">
                    <select name="type" class="form-select" required>
                        @foreach($sectionTypes as $type)
                            <option value="{{ $type }}">{{ __('public_site.section_'.$type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">{{ __('public_site.add_section') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if($sections->isNotEmpty())
        <form method="post" action="{{ route('site.homepage.sections.reorder') }}" class="mb-4">
            @csrf
            <label class="form-label">{{ __('public_site.reorder_sections') }}</label>
            <select name="order[]" class="form-select mb-2" multiple size="{{ min(8, $sections->count()) }}">
                @foreach($sections as $section)
                    <option value="{{ $section->church_site_section_id }}" selected>
                        {{ __('public_site.section_'.$section->type) }} (#{{ $section->sort_order }})
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('public_site.save_order') }}</button>
        </form>
    @endif

    @forelse($sections as $section)
        @php $content = $section->content_draft ?? []; @endphp
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ __('public_site.section_'.$section->type) }}</strong>
                <form method="post" action="{{ route('site.homepage.sections.destroy', $section) }}" onsubmit="return confirm('{{ __('public_site.confirm_delete_section') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('public_site.delete_section') }}</button>
                </form>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('site.homepage.sections.update', $section) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="enabled_draft" value="0">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="enabled_draft" value="1" id="enabled_{{ $section->church_site_section_id }}" @checked($section->enabled_draft)>
                        <label class="form-check-label" for="enabled_{{ $section->church_site_section_id }}">{{ __('public_site.section_enabled') }}</label>
                    </div>

                    @if($section->type === 'hero')
                        <div class="row g-2">
                            <div class="col-md-6"><input class="form-control" name="headline_ar" placeholder="{{ __('public_site.headline_ar') }}" value="{{ old('headline_ar', $content['headline_ar'] ?? '') }}"></div>
                            <div class="col-md-6"><input class="form-control" name="headline_en" placeholder="{{ __('public_site.headline_en') }}" value="{{ old('headline_en', $content['headline_en'] ?? '') }}"></div>
                            <div class="col-md-6"><textarea class="form-control" name="sub_ar" rows="2" placeholder="{{ __('public_site.sub_ar') }}">{{ old('sub_ar', $content['sub_ar'] ?? '') }}</textarea></div>
                            <div class="col-md-6"><textarea class="form-control" name="sub_en" rows="2" placeholder="{{ __('public_site.sub_en') }}">{{ old('sub_en', $content['sub_en'] ?? '') }}</textarea></div>
                            <div class="col-md-6">
                                <select name="image_media_id" class="form-select">
                                    <option value="">{{ __('public_site.no_image') }}</option>
                                    @foreach($media as $m)
                                        <option value="{{ $m->church_media_id }}" @selected((int)($content['image_media_id'] ?? 0) === $m->church_media_id)>#{{ $m->church_media_id }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><input class="form-control" name="cta_label_ar" placeholder="{{ __('public_site.cta_label_ar') }}" value="{{ old('cta_label_ar', $content['cta_label_ar'] ?? '') }}"></div>
                            <div class="col-md-3"><input class="form-control" name="cta_url" placeholder="{{ __('public_site.cta_url') }}" value="{{ old('cta_url', $content['cta_url'] ?? '') }}"></div>
                        </div>
                    @elseif($section->type === 'about')
                        <div class="row g-2">
                            <div class="col-md-6"><input class="form-control" name="title_ar" value="{{ $content['title_ar'] ?? '' }}"></div>
                            <div class="col-md-6"><input class="form-control" name="title_en" value="{{ $content['title_en'] ?? '' }}"></div>
                            <div class="col-md-6"><textarea class="form-control" name="body_ar" rows="3">{{ $content['body_ar'] ?? '' }}</textarea></div>
                            <div class="col-md-6"><textarea class="form-control" name="body_en" rows="3">{{ $content['body_en'] ?? '' }}</textarea></div>
                            <div class="col-md-6">
                                <select name="image_media_id" class="form-select">
                                    <option value="">{{ __('public_site.no_image') }}</option>
                                    @foreach($media as $m)
                                        <option value="{{ $m->church_media_id }}" @selected((int)($content['image_media_id'] ?? 0) === $m->church_media_id)>#{{ $m->church_media_id }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @elseif($section->type === 'gallery')
                        <input class="form-control" name="media_ids" value="{{ implode(',', $content['media_ids'] ?? []) }}" placeholder="{{ __('public_site.gallery_ids_hint') }}">
                    @elseif($section->type === 'quote')
                        <div class="row g-2">
                            <div class="col-md-6"><textarea class="form-control" name="text_ar" rows="2">{{ $content['text_ar'] ?? '' }}</textarea></div>
                            <div class="col-md-6"><textarea class="form-control" name="text_en" rows="2">{{ $content['text_en'] ?? '' }}</textarea></div>
                            <div class="col-md-6"><input class="form-control" name="citation_ar" value="{{ $content['citation_ar'] ?? '' }}"></div>
                            <div class="col-md-6"><input class="form-control" name="citation_en" value="{{ $content['citation_en'] ?? '' }}"></div>
                        </div>
                    @elseif($section->type === 'cta_portal')
                        <div class="row g-2">
                            <div class="col-md-6"><input class="form-control" name="headline_ar" value="{{ $content['headline_ar'] ?? '' }}"></div>
                            <div class="col-md-6"><input class="form-control" name="headline_en" value="{{ $content['headline_en'] ?? '' }}</div>
                        </div>
                    @else
                        <p class="text-muted small mb-0">{{ __('public_site.section_uses_profile', ['type' => __('public_site.section_'.$section->type)]) }}</p>
                        <input type="hidden" name="use_profile" value="1">
                    @endif

                    <button type="submit" class="btn btn-primary btn-sm mt-3">{{ __('public_site.save_section') }}</button>
                </form>
            </div>
        </div>
    @empty
        <p class="text-muted">{{ __('public_site.no_sections_yet') }}</p>
    @endforelse

    <div class="card mt-4">
        <div class="card-body">
            <h2 class="h6">{{ __('public_site.upload_media') }}</h2>
            <form method="post" action="{{ route('site.media.store') }}" enctype="multipart/form-data" class="row g-2">
                @csrf
                <div class="col-md-4"><input type="file" name="file" class="form-control" accept="image/*" required></div>
                <div class="col-md-3"><input class="form-control" name="alt_ar" placeholder="{{ __('public_site.alt_ar') }}"></div>
                <div class="col-md-3"><input class="form-control" name="alt_en" placeholder="{{ __('public_site.alt_en') }}"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100">{{ __('public_site.upload') }}</button></div>
            </form>
            @if($media->isNotEmpty())
                <ul class="list-group list-group-flush mt-3">
                    @foreach($media as $m)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>#{{ $m->church_media_id }} — {{ basename($m->path) }}</span>
                            <form method="post" action="{{ route('site.media.destroy', $m) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('public_site.delete_media') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
