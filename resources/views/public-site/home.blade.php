@extends('layouts.public-site')

@section('content')
    @forelse($sections as $section)
        @php
            $content = ($preview ?? false) ? ($section->content_draft ?? []) : ($section->content_published ?? []);
        @endphp
        @if(view()->exists('public-site.sections.'.$section->type))
            @include('public-site.sections.'.$section->type, [
                'content' => $content,
                'church' => $church,
            ])
        @endif
    @empty
        <section class="ps-section">
            <div class="container text-center py-5">
                <p class="text-muted mb-0">{{ __('public_site.no_sections') }}</p>
            </div>
        </section>
    @endforelse
@endsection
