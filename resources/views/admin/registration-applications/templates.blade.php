@extends('layouts.app')

@section('title', __('registration_review.templates_title'))

@section('content')
@php
    use App\Models\RegistrationReviewTemplate;
@endphp
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('registration_review.templates_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('registration_review.templates_intro') }}</p>
        </div>
        <a href="{{ route('admin.registration-applications.index') }}" class="btn btn-outline-secondary btn-sm">
            {{ __('pages.back') }}
        </a>
    </div>

<p class="small text-muted-theme">{{ __('registration_review.placeholders_help') }}</p>

    <form method="POST" action="{{ route('admin.registration-applications.templates.update') }}">
        @csrf
        @method('PUT')

        @foreach(RegistrationReviewTemplate::keys() as $templateKey)
            <div class="app-card card shadow-sm mb-4">
                <div class="card-header fw-semibold">{{ __('registration_review.email_subjects.'.$templateKey) }}</div>
                <div class="card-body">
                    @foreach($templates[$templateKey] ?? [] as $template)
                        <div class="border rounded p-3 mb-3">
                            <div class="fw-semibold mb-2">{{ strtoupper($template->locale) }}</div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('registration_review.email_subjects.'.$templateKey) }} ({{ strtoupper($template->locale) }})</label>
                                <input type="text" name="templates[{{ $template->id }}][subject]"
                                       class="form-control" value="{{ old("templates.{$template->id}.subject", $template->subject) }}" required>
                            </div>
                            <div>
                                <label class="form-label">HTML</label>
                                <textarea name="templates[{{ $template->id }}][body_html]" rows="6"
                                          class="form-control font-monospace" required>{{ old("templates.{$template->id}.body_html", $template->body_html) }}</textarea>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
    </form>
</div>
@endsection
