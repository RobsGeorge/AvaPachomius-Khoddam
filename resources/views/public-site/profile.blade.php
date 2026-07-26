<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $church->name }} — {{ __('public_site.public_page_title') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap{{ app()->getLocale() === 'ar' ? '.rtl' : '' }}.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width:760px;">
    <h1 class="display-6 mb-2">{{ $church->name }}</h1>
    @if(($profile['show_on_public_site']['tagline'] ?? true) && ($t = \App\Support\PublicSite\ChurchPublicProfile::localized($profile['tagline'])))
        <p class="lead text-secondary">{{ $t }}</p>
    @endif

    @if(($profile['show_on_public_site']['about'] ?? true) && ($about = \App\Support\PublicSite\ChurchPublicProfile::localized($profile['about'])))
        <section class="mb-4">
            <h2 class="h5">{{ __('public_site.about') }}</h2>
            <p class="mb-0" style="white-space:pre-line;">{{ $about }}</p>
        </section>
    @endif

    @if(($profile['show_on_public_site']['address'] ?? true) && ($profile['address'] || $profile['city']))
        <section class="mb-4">
            <h2 class="h5">{{ __('public_site.address') }}</h2>
            <p class="mb-0">{{ trim($profile['address'].($profile['city'] ? ' — '.$profile['city'] : '')) }}</p>
        </section>
    @endif

    @if($profile['show_on_public_site']['contact'] ?? true)
        <section class="mb-4">
            <h2 class="h5">{{ __('public_site.contact') }}</h2>
            <ul class="list-unstyled mb-0">
                @if($profile['phone'])<li>{{ __('public_site.phone') }}: {{ $profile['phone'] }}</li>@endif
                @if($profile['whatsapp'])<li>WhatsApp: {{ $profile['whatsapp'] }}</li>@endif
                @if($profile['email'])<li>{{ __('public_site.email') }}: <a href="mailto:{{ $profile['email'] }}">{{ $profile['email'] }}</a></li>@endif
            </ul>
        </section>
    @endif

    @if($profile['show_on_public_site']['social'] ?? true)
        <section class="mb-4">
            <h2 class="h5">{{ __('public_site.social') }}</h2>
            <ul class="list-unstyled mb-0">
                @foreach(['facebook','youtube','instagram'] as $net)
                    @if(!empty($profile['social'][$net]))
                        <li><a href="{{ $profile['social'][$net] }}" rel="noopener" target="_blank">{{ ucfirst($net) }}</a></li>
                    @endif
                @endforeach
            </ul>
        </section>
    @endif

    @if(($profile['show_on_public_site']['liturgy_hours'] ?? true) && !empty($profile['liturgy_hours']))
        <section class="mb-4">
            <h2 class="h5">{{ __('public_site.liturgy_hours') }}</h2>
            <ul class="mb-0">
                @foreach($profile['liturgy_hours'] as $row)
                    <li>
                        <strong>{{ $row['day'] ?? '' }}</strong>
                        — {{ \App\Support\PublicSite\ChurchPublicProfile::localized($row['time'] ?? []) }}
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <p class="mt-4"><a href="{{ route('login') }}">{{ __('nav.login') }}</a></p>
</main>
</body>
</html>
