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

                        @if(auth()->user()->hasAnyRole(['admin', 'instructor']))
                            <a href="{{ route('attendance.all') }}" class="app-nav-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
                                {{ __('nav.attendance') }}
                            </a>
                        @else
                            <a href="{{ route('attendance.my') }}" class="app-nav-link {{ request()->routeIs('attendance.my') ? 'active' : '' }}">
                                {{ __('nav.my_attendance') }}
                            </a>
                        @endif

                        <a href="{{ route('sessions.index') }}" class="app-nav-link {{ request()->routeIs('sessions.*') ? 'active' : '' }}">
                            {{ __('nav.sessions') }}
                        </a>

                        @if(auth()->user()->roles->contains('role_name', 'admin'))
                            <a href="{{ route('user-course-roles.index') }}" class="app-nav-link {{ request()->routeIs('user-course-roles.*', 'roles.*') ? 'active' : '' }}">
                                {{ __('nav.roles') }}
                            </a>
                            <a href="{{ route('admin.translations.index') }}" class="app-nav-link {{ request()->routeIs('admin.translations.*') ? 'active' : '' }}">
                                {{ __('nav.translations') }}
                            </a>
                        @endif

                        @if(auth()->user()->is_superadmin)
                            <a href="{{ route('superadmin.index') }}" class="app-nav-link {{ request()->routeIs('superadmin.*') ? 'active' : '' }}">
                                <i class="bi bi-shield-lock-fill"></i> {{ __('nav.superadmin') }}
                            </a>
                        @endif
                    </div>
                @endauth
            </div>

            <div class="d-flex align-items-center gap-2">
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
            <div class="d-md-none nav-links mt-2" x-show="navOpen" x-transition>
                <div class="d-flex flex-column gap-1">
                    <a href="{{ route('dashboard') }}" class="app-nav-link">{{ __('nav.home') }}</a>
                    @if(auth()->user()->hasAnyRole(['admin', 'instructor']))
                        <a href="{{ route('attendance.all') }}" class="app-nav-link">{{ __('nav.attendance') }}</a>
                    @else
                        <a href="{{ route('attendance.my') }}" class="app-nav-link">{{ __('nav.my_attendance') }}</a>
                    @endif
                    <a href="{{ route('sessions.index') }}" class="app-nav-link">{{ __('nav.sessions') }}</a>
                    @if(auth()->user()->roles->contains('role_name', 'admin'))
                        <a href="{{ route('user-course-roles.index') }}" class="app-nav-link">{{ __('nav.roles') }}</a>
                        <a href="{{ route('admin.translations.index') }}" class="app-nav-link">{{ __('nav.translations') }}</a>
                    @endif
                    <a href="{{ route('profile') }}" class="app-nav-link">{{ __('nav.profile') }}</a>
                    <a href="{{ route('logout') }}" class="app-nav-link">{{ __('nav.logout') }}</a>
                </div>
            </div>
        @endauth
    </div>
</nav>
