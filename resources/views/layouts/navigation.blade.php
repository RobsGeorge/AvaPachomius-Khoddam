@php
    use App\Support\NavigationHub;
    $navUser = auth()->user();
    $academicLinks = NavigationHub::academicLinks($navUser);
    $serviceLinks = NavigationHub::serviceLinks($navUser);
    $systemLinks = NavigationHub::systemLinks($navUser);
    $superadminSections = NavigationHub::superadminSections($navUser);
    $hasService = NavigationHub::hasService($navUser);
    $hasSystem = NavigationHub::hasSystem($navUser);
    $hasSuperadmin = NavigationHub::hasSuperadmin($navUser);
    $academicActive = NavigationHub::isAcademicActive($navUser);
    $serviceActive = NavigationHub::isServiceActive($navUser);
    $systemActive = NavigationHub::isSystemActive($navUser);
    $superadminActive = NavigationHub::isSuperadminActive($navUser);
@endphp
<nav class="app-nav sticky-top">
    <div class="container py-2">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                @auth
                    {{-- Church → Service → Course (host-based church switch when MULTI_TENANT). --}}
                    @if(!empty($supportsChurchSwitcher))
                        <div class="dropdown">
                            <button type="button"
                                    class="brand-link dropdown-toggle border-0 bg-transparent p-0"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                    aria-label="{{ __('tenancy.switch_church') }}">
                                <i class="bi bi-building ms-1"></i>
                                @if(!empty($currentChurch))
                                    {{ $currentChurch->name }}
                                @elseif(!empty($isConsoleHost))
                                    {{ __('tenancy.console') }}
                                @else
                                    {{ __('tenancy.switch_church') }}
                                @endif
                            </button>
                            <ul class="dropdown-menu app-dropdown-panel">
                                @foreach($selectableChurches ?? [] as $switchChurch)
                                    <li>
                                        <a class="dropdown-item app-dropdown-link {{ ($currentChurch->church_id ?? null) === $switchChurch->church_id ? 'active fw-semibold' : '' }}"
                                           href="{{ \App\Support\ChurchHost::pathPreservingUrl($switchChurch) }}">
                                            {{ $switchChurch->name }}
                                        </a>
                                    </li>
                                @endforeach
                                @if($navUser->is_superadmin ?? false)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item app-dropdown-link" href="{{ \App\Support\ChurchHost::consoleUrl('/superadmin/churches') }}">
                                            <i class="bi bi-gear me-2"></i>{{ __('tenancy.nav_churches') }}
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @elseif(!empty($showChurchContextLabel) && !empty($labeledChurch))
                        <span class="brand-link" title="{{ __('tenancy.current_church') }}">
                            <i class="bi bi-building ms-1"></i>
                            {{ $labeledChurch->name }}
                        </span>
                    @endif

                    {{-- Service first, then course (service owns courses). --}}
                    @if(!empty($supportsServiceSwitcher))
                        <div class="dropdown">
                            <button type="button"
                                    class="brand-link dropdown-toggle border-0 bg-transparent p-0"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                    aria-label="{{ __('service.switch_service') }}">
                                <i class="fas fa-church ms-1"></i>
                                @if(!empty($currentService))
                                    {{ $currentService->localizedTitle() }}
                                @elseif($navUser->is_superadmin ?? false)
                                    {{ __('service.system_wide_mode') }}
                                @else
                                    {{ __('service.select_title') }}
                                @endif
                            </button>
                            <ul class="dropdown-menu app-dropdown-panel">
                                @if($navUser->is_superadmin ?? false)
                                    <li>
                                        <form method="POST" action="{{ route('services.select.clear') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item app-dropdown-link {{ empty($currentService) ? 'active fw-semibold' : '' }}">
                                                <i class="bi bi-globe2 me-2"></i>{{ __('service.system_wide_mode') }}
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                @endif
                                @foreach($selectableServices ?? [] as $switchService)
                                    <li>
                                        <form method="POST" action="{{ route('services.select.store') }}">
                                            @csrf
                                            <input type="hidden" name="service_id" value="{{ $switchService->service_id }}">
                                            <button type="submit"
                                                    class="dropdown-item app-dropdown-link {{ ($currentService->service_id ?? null) === $switchService->service_id ? 'active fw-semibold' : '' }}">
                                                {{ $switchService->localizedTitle() }}
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item app-dropdown-link" href="{{ route('services.select') }}">
                                        <i class="bi bi-grid me-2"></i>{{ __('service.switch_service') }}
                                    </a>
                                </li>
                                @if($hasService)
                                    <li>
                                        <a class="dropdown-item app-dropdown-link" href="{{ route('hubs.service') }}">
                                            <i class="fas fa-church me-2"></i>{{ __('nav.service') }}
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @elseif(!empty($showServiceContextLabel) && !empty($currentService))
                        <span class="brand-link" title="{{ __('service.current_service') }}">
                            <i class="fas fa-church ms-1"></i>
                            {{ $currentService->localizedTitle() }}
                        </span>
                    @endif

                    @if(!empty($supportsCourseSwitcher))
                        @if(!empty($currentCourse))
                            <div class="dropdown">
                                <button type="button"
                                        class="brand-link dropdown-toggle border-0 bg-transparent p-0"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        aria-label="{{ __('course_context.switch_course') }}">
                                    <i class="bi bi-mortarboard-fill ms-1"></i>
                                    {{ $currentCourse->localizedTitle() }}
                                </button>
                                <ul class="dropdown-menu app-dropdown-panel">
                                    @if($navUser->is_superadmin ?? false)
                                        <li>
                                            <form method="POST" action="{{ route('courses.select.clear') }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item app-dropdown-link">
                                                    <i class="bi bi-globe2 me-2"></i>{{ __('course_context.system_wide_mode') }}
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                    @endif
                                    @foreach($selectableCourses ?? [] as $row)
                                        @php $switchCourse = $row['course']; @endphp
                                        <li>
                                            <form method="POST" action="{{ route('courses.select.store') }}">
                                                @csrf
                                                <input type="hidden" name="course_id" value="{{ $switchCourse->course_id }}">
                                                <button type="submit"
                                                        class="dropdown-item app-dropdown-link {{ ($currentCourse->course_id ?? null) === $switchCourse->course_id ? 'active fw-semibold' : '' }}">
                                                    {{ $switchCourse->localizedTitle() }}
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item app-dropdown-link" href="{{ route('courses.select') }}">
                                            <i class="bi bi-grid me-2"></i>{{ __('course_context.switch_course') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        @elseif($navUser->is_superadmin ?? false)
                            <div class="dropdown">
                                <button type="button"
                                        class="brand-link dropdown-toggle border-0 bg-transparent p-0"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        aria-label="{{ __('course_context.switch_course') }}">
                                    <i class="bi bi-shield-lock-fill ms-1"></i>
                                    {{ __('nav.superadmin') }}
                                </button>
                                <ul class="dropdown-menu app-dropdown-panel">
                                    <li>
                                        <span class="dropdown-item app-dropdown-link active fw-semibold">
                                            <i class="bi bi-globe2 me-2"></i>{{ __('course_context.system_wide_mode') }}
                                        </span>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    @foreach($selectableCourses ?? [] as $row)
                                        @php $switchCourse = $row['course']; @endphp
                                        <li>
                                            <form method="POST" action="{{ route('courses.select.store') }}">
                                                @csrf
                                                <input type="hidden" name="course_id" value="{{ $switchCourse->course_id }}">
                                                <button type="submit" class="dropdown-item app-dropdown-link">
                                                    {{ $switchCourse->localizedTitle() }}
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item app-dropdown-link" href="{{ route('courses.select') }}">
                                            <i class="bi bi-grid me-2"></i>{{ __('course_context.switch_course') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        @else
                            <a href="{{ route('courses.select') }}" class="brand-link">
                                <i class="bi bi-mortarboard-fill ms-1"></i>
                                {{ $instituteName ?? __('app.institute_name') }}
                            </a>
                        @endif
                    @elseif(!empty($showCourseContextLabel) && !empty($currentCourse))
                        <span class="brand-link" title="{{ __('course_context.current_course') }}">
                            <i class="bi bi-mortarboard-fill ms-1"></i>
                            {{ $currentCourse->localizedTitle() }}
                        </span>
                    @else
                        <a href="{{ route('dashboard') }}" class="brand-link">
                            <i class="bi bi-mortarboard-fill ms-1"></i>
                            {{ __('app.name') }}
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="brand-link">
                        <i class="bi bi-mortarboard-fill ms-1"></i>
                        {{ __('app.name') }}
                    </a>
                @endauth

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
                                            @include('partials.icon', ['icon' => $link['icon'], 'class' => 'me-2']){{ $link['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @if($hasService)
                            <div class="dropdown">
                                <button class="app-nav-link dropdown-toggle border-0 bg-transparent {{ $serviceActive ? 'active' : '' }}"
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ __('nav.service') }}
                                </button>
                                <ul class="dropdown-menu app-dropdown-panel">
                                    <li>
                                        <a class="dropdown-item app-dropdown-link fw-semibold {{ request()->routeIs('hubs.service') ? 'active' : '' }}"
                                           href="{{ route('hubs.service') }}">
                                            <i class="fas fa-church me-2"></i>{{ __('nav.service') }}
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    @foreach($serviceLinks as $link)
                                        <li>
                                            <a class="dropdown-item app-dropdown-link {{ $link['active'] ? 'active fw-semibold' : '' }}"
                                               href="{{ $link['url'] }}">
                                                @include('partials.icon', ['icon' => $link['icon'], 'class' => 'me-2']){{ $link['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

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
                                                @include('partials.icon', ['icon' => $link['icon'], 'class' => 'me-2']){{ $link['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($hasSuperadmin)
                            <div class="dropdown">
                                <button class="app-nav-link dropdown-toggle border-0 bg-transparent {{ $superadminActive ? 'active' : '' }}"
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    @include('partials.superadmin-entry-tag', ['class' => 'me-1'])
                                    {{ __('nav.superadmin') }}
                                </button>
                                <ul class="dropdown-menu app-dropdown-panel">
                                    @foreach($superadminSections as $section)
                                        <li><h6 class="dropdown-header">{{ $section['title'] }}</h6></li>
                                        @foreach($section['links'] as $link)
                                            <li>
                                                @include('partials.nav-hub-link', ['link' => $link])
                                            </li>
                                        @endforeach
                                        @if(! $loop->last)
                                            <li><hr class="dropdown-divider"></li>
                                        @endif
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
                        @if(!empty($unreadNotificationBadge))
                            <span class="nav-notification-badge">{{ $unreadNotificationBadge }}</span>
                        @endif
                    </a>

                    <div class="dropdown d-none d-md-block">
                        <button class="app-toolbar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            {{ auth()->user()->first_name }}
                        </button>
                        <ul class="dropdown-menu app-dropdown-panel dropdown-menu-end">
                            @if(auth()->user()->isStudent())
                                <li><a class="dropdown-item app-dropdown-link {{ request()->routeIs('my-learning.*') ? 'active' : '' }}" href="{{ route('my-learning.index') }}">{{ __('nav.my_learning') }}</a></li>
                            @endif
                            <li><a class="dropdown-item app-dropdown-link" href="{{ route('profile') }}">{{ __('nav.profile') }}</a></li>
                            <li><a class="dropdown-item app-dropdown-link {{ request()->routeIs('account.*') ? 'active' : '' }}" href="{{ route('account.index') }}">{{ __('nav.account') }}</a></li>
                            <li><a class="dropdown-item app-dropdown-link {{ request()->routeIs('help.*') ? 'active' : '' }}" href="{{ route('help.faq') }}">{{ __('nav.help') }}</a></li>
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
                                    @include('partials.icon', ['icon' => $link['icon'], 'class' => 'me-1']){{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </details>

                    @if($hasService)
                        <details class="mobile-nav-group" @if($serviceActive) open @endif>
                            <summary class="mobile-nav-summary {{ $serviceActive ? 'active' : '' }}">
                                <span>{{ __('nav.service') }}</span>
                                <i class="bi bi-chevron-down mobile-nav-chevron" aria-hidden="true"></i>
                            </summary>
                            <div class="mobile-nav-submenu d-flex flex-column gap-1">
                                <a href="{{ route('hubs.service') }}" class="app-nav-link small {{ request()->routeIs('hubs.service') ? 'active' : '' }}" @click="navOpen = false">
                                    <i class="fas fa-church me-1"></i>{{ __('nav.service') }}
                                </a>
                                @foreach($serviceLinks as $link)
                                    <a href="{{ $link['url'] }}" class="app-nav-link small {{ $link['active'] ? 'active' : '' }}" @click="navOpen = false">
                                        @include('partials.icon', ['icon' => $link['icon'], 'class' => 'me-1']){{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endif

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
                                        @include('partials.icon', ['icon' => $link['icon'], 'class' => 'me-1']){{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    @if($hasSuperadmin)
                        <details class="mobile-nav-group" @if($superadminActive) open @endif>
                            <summary class="mobile-nav-summary {{ $superadminActive ? 'active' : '' }}">
                                <span>
                                    @include('partials.superadmin-entry-tag', ['class' => 'me-1'])
                                    {{ __('nav.superadmin') }}
                                </span>
                                <i class="bi bi-chevron-down mobile-nav-chevron" aria-hidden="true"></i>
                            </summary>
                            <div class="mobile-nav-submenu d-flex flex-column gap-1">
                                @foreach($superadminSections as $section)
                                    <div class="small text-muted-theme text-uppercase px-2 pt-2">{{ $section['title'] }}</div>
                                    @foreach($section['links'] as $link)
                                        @include('partials.nav-hub-link', [
                                            'link' => $link,
                                            'linkClass' => 'app-nav-link small',
                                            'clickClose' => true,
                                        ])
                                    @endforeach
                                @endforeach
                            </div>
                        </details>
                    @endif

                    <a href="{{ route('notifications.index') }}" class="app-nav-link d-flex align-items-center gap-2 {{ request()->routeIs('notifications.*') ? 'active' : '' }}" @click="navOpen = false">
                        <i class="bi bi-bell"></i>
                        {{ __('notifications.hub_title') }}
                        @if(!empty($unreadNotificationBadge))
                            <span class="badge bg-danger">{{ $unreadNotificationBadge }}</span>
                        @endif
                    </a>

                    <hr class="my-1 border-secondary-subtle">
                    @if(auth()->user()->isStudent())
                        <a href="{{ route('my-learning.index') }}" class="app-nav-link {{ request()->routeIs('my-learning.*') ? 'active' : '' }}" @click="navOpen = false">{{ __('nav.my_learning') }}</a>
                    @endif
                    <a href="{{ route('profile') }}" class="app-nav-link {{ request()->routeIs('profile') ? 'active' : '' }}" @click="navOpen = false">{{ __('nav.profile') }}</a>
                    <a href="{{ route('account.index') }}" class="app-nav-link {{ request()->routeIs('account.*') ? 'active' : '' }}" @click="navOpen = false">{{ __('nav.account') }}</a>
                    <a href="{{ route('help.faq') }}" class="app-nav-link {{ request()->routeIs('help.*') ? 'active' : '' }}" @click="navOpen = false">{{ __('nav.help') }}</a>
                    <a href="{{ route('logout') }}" class="app-nav-link" @click="navOpen = false">{{ __('nav.logout') }}</a>
                </div>
            </div>
        @endauth
    </div>
</nav>
