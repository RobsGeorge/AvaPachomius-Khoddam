@php
    use App\Support\PublicSite\ChurchPublicProfile;
    $profile = ChurchPublicProfile::fromSettings($church->settings ?? []);
    $hours = ($content['use_profile'] ?? true) ? ($profile['liturgy_hours'] ?? []) : [];
@endphp
<section class="ps-section ps-section-alt">
    <div class="container">
        <h2>{{ __('public_site.liturgy_hours') }}</h2>
        @if($hours)
            <ul class="list-unstyled mb-0">
                @foreach($hours as $row)
                    <li class="mb-2">
                        <strong>{{ $row['day'] ?? '' }}</strong>
                        — {{ app()->getLocale() === 'ar' ? ($row['time_ar'] ?? $row['time_en'] ?? '') : ($row['time_en'] ?? $row['time_ar'] ?? '') }}
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted mb-0">{{ __('public_site.liturgy_empty') }}</p>
        @endif
    </div>
</section>
