@extends('layouts.app')

@section('title', __('help.title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">
    <h1 class="page-title mb-1">{{ __('help.title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('help.subtitle') }}</p>

    <div class="accordion" id="faqAccordion">
        @foreach($faqs as $i => $faq)
            <div class="accordion-item">
                <h2 class="accordion-header" id="faq-heading-{{ $i }}">
                    <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                            data-bs-toggle="collapse" data-bs-target="#faq-body-{{ $i }}"
                            aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="faq-body-{{ $i }}">
                        {{ $faq['q'] }}
                    </button>
                </h2>
                <div id="faq-body-{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                     aria-labelledby="faq-heading-{{ $i }}" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-muted-theme">{{ $faq['a'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="app-card card shadow-sm mt-4">
        <div class="card-body">
            <div class="fw-semibold"><i class="bi bi-life-preserver" aria-hidden="true"></i> {{ __('help.contact_heading') }}</div>
            <p class="text-muted-theme small mb-0">{{ __('help.contact_body') }}</p>
        </div>
    </div>
</div>
@endsection
