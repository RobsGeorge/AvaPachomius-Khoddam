@php
    use App\Support\NavigationHub;
    $navUser = auth()->user();
    $academicLinks = NavigationHub::academicLinks($navUser);
    $systemLinks = NavigationHub::systemLinks($navUser);
    $hasSystem = NavigationHub::hasSystem($navUser);
    $academicActive = NavigationHub::isAcademicActive($navUser);
    $systemActive = NavigationHub::isSystemActive($navUser);
@endphp
<nav class="app-nav sticky-top">
    <div class="container py-2">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="brand-link">
                    <i class="bi bi-mortarboard-fill ms-1"></i>
                    {{ __('app.name') }}
                </a>

                @auth
                    <div class="d-none d-md-flex align-items-center gap-1 nav-links">
                        <a href="{{ route('dashboard') }}" class="app-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            {{ __('nav.home') }}
                        </a>

                        <div class="dropdown">
                            <button class="app-nav-link dropdown-toggle border-0 bg-transparent {{ $academicActive ? 'active' : '' }}"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ __('nav.academic') }}
                            </button>
                            <ul class="dropdown-menu app-dropdown-panel">
                                <li>
                                    <a class="dropdown-item app-dropdown-link fw-semibold {{ request()->routeIs('hubs.academic') ? 'active' : '' }}"
                                       href="{{ route('hubs.academic') }}">
                                        <i class="bi bi-grid me-2"></i>{{ __('nav.academic') }}
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @foreach($academicLinks as $link)
                                    <li>
                                        <a class="dropdown-item app-dropdown-link {{ $link['active'] ? 'active fw-semibold' : '' }}"
                                           href="{{ $link['url'] }}">
                                            <i class="bi {{ $link['icon'] }} me-2"></i>{{ $link['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @if($hasSystem)
                            <div class="dropdown">
                                <button class="app-nav-link dropdown-toggle border-0 bg-transparent {{ $systemActive ? 'active' : '' }}"
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ __('nav.system_settings') }}
                                </button>
                                <ul class="dropdown-menu app-dropdown-panel">
                                    <li>
                                        <a class="dropdown-item app-dropdown-link fw-semibold {{ request()->routeIs('hubs.system') ? 'active' : '' }}"
                                           href="{{ route('hubs.system') }}">
                                            <i class="bi bi-gear me-2"></i>{{ __('nav.system_settings') }}
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    @foreach($systemLinks as $link)
                                        <li>
                                            <a class="dropdown-item app-dropdown-link {{ $link['active'] ? 'active fw-semibold' : '' }}"
                                               href="{{ $link['url'] }}">
                                                <i class="bi {{ $link['icon'] }} me-2"></i>{{ $link['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endauth
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <div class="dropdown">
                    <button class="app-toolbar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-translate"></i>
                        {{ config('translation.locale_labels.' . app()->getLocale()) }}
                    </button>
                    <ul class="dropdown-menu app-dropdown-panel dropdown-menu-end">
                        @foreach(config('translation.supported_locales', ['ar', 'en']) as $localeCode)
                            <li>
                                <a class="dropdown-item app-dropdown-link {{ app()->getLocale() === $localeCode ? 'fw-bold' : '' }}"
                                   href="{{ route('locale.switch', $localeCode) }}">
                                    {{ config('translation.locale_labels.' . $localeCode) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <button type="button" class="app-toolbar-btn" data-theme-toggle
                        data-label-light="{{ __('app.theme_light') }}"
                        data-label-dark="{{ __('app.theme_dark') }}"
                        aria-pressed="false">
                    <i class="bi bi-moon-stars"></i>
                    <span data-theme-label>{{ __('app.theme_dark') }}</span>
                </button>

                @auth
                    <a href="{{ route('notifications.index') }}" class="app-toolbar-btn position-relative text-decoration-none" aria-label="{{ __('notifications.hub_title') }}">
                        <i class="bi bi-bell"></i>
                        @if(!empty($unreadNotificationCount) && $unreadNotificationCount > 0)
                            <span class="nav-notification-badge">{{ $unreadNotificationCount }}</span>
                        @endif
                    </a>

                    <div class="dropdown d-none d-md-block">
                        <button class="app-toolbar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            {{ auth()->user()->first_name }}
                        </button>
                        <ul class="dropdown-menu app-dropdown-panel dropdown-menu-end">
                            <li><a class="dropdown-item app-dropdown-link" href="{{ route('profile') }}">{{ __('nav.profile') }}</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item app-dropdown-link" href="{{ route('logout') }}">{{ __('nav.logout') }}</a></li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="app-nav-link">{{ __('nav.login') }}</a>
                @endauth

                @auth
                    <button class="app-toolbar-btn d-md-none" type="button" @click="navOpen = !navOpen" aria-label="Menu">
                        <i class="bi bi-list"></i>
                    </button>
                @endauth
            </div>
        </div>

        @auth
            <div class="d-md-none nav-links mobile-nav-panel mt-2 pb-2" x-show="navOpen" x-transition x-cloak>
                <div class="d-flex flex-column gap-1">
                    <a href="{{ route('dashboard') }}" class="app-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" @click="navOpen = false">{{ __('nav.home') }}</a>

                    <details class="mobile-nav-group" @if($academicActive) open @endif>
                        <summary class="mobile-nav-summary {{ $academicActive ? 'active' : '' }}">
                            <span>{{ __('nav.academic') }}</span>
                            <i class="bi bi-chevron-down mobile-nav-chevron" aria-hidden="true"></i>
                        </summary>
                        <div class="mobile-nav-submenu d-flex flex-column gap-1">
                            <a href="{{ route('hubs.academic') }}" class="app-nav-link small {{ request()->routeIs('hubs.academic') ? 'active' : '' }}" @click="navOpen = false">
                                <i class="bi bi-grid me-1"></i>{{ __('nav.academic') }}
                            </a>
                            @foreach($academicLinks as $link)
                                <a href="{{ $link['url'] }}" class="app-nav-link small {{ $link['active'] ? 'active' : '' }}" @click="navOpen = false">
                                    <i class="bi {{ $link['icon'] }} me-1"></i>{{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </details>

                    @if($hasSystem)
                        <details class="mobile-nav-group" @if($systemActive) open @endif>
                            <summary class="mobile-nav-summary {{ $systemActive ? 'active' : '' }}">
                                <span>{{ __('nav.system_settings') }}</span>
                                <i class="bi bi-chevron-down mobile-nav-chevron" aria-hidden="true"></i>
                            </summary>
                            <div class="mobile-nav-submenu d-flex flex-column gap-1">
                                <a href="{{ route('hubs.system') }}" class="app-nav-link small {{ request()->routeIs('hubs.system') ? 'active' : '' }}" @click="navOpen = false">
                                    <i class="bi bi-gear me-1"></i>{{ __('nav.system_settings') }}
                                </a>
                                @foreach($systemLinks as $link)
                                    <a href="{{ $link['url'] }}" class="app-nav-link small {{ $link['active'] ? 'active' : '' }}" @click="navOpen = false">
                                        <i class="bi {{ $link['icon'] }} me-1"></i>{{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    <a href="{{ route('notifications.index') }}" class="app-nav-link d-flex align-items-center gap-2 {{ request()->routeIs('notifications.*') ? 'active' : '' }}" @click="navOpen = false">
                        <i class="bi bi-bell"></i>
                        {{ __('notifications.hub_title') }}
                        @if(!empty($unreadNotificationCount) && $unreadNotificationCount > 0)
                            <span class="badge bg-danger">{{ $unreadNotificationCount }}</span>
                        @endif
                    </a>

                    <hr class="my-1 border-secondary-subtle">
                    <a href="{{ route('profile') }}" class="app-nav-link {{ request()->routeIs('profile') ? 'active' : '' }}" @click="navOpen = false">{{ __('nav.profile') }}</a>
                    <a href="{{ route('logout') }}" class="app-nav-link" @click="navOpen = false">{{ __('nav.logout') }}</a>
                </div>
            </div>
        @endauth
    </div>
</nav>
