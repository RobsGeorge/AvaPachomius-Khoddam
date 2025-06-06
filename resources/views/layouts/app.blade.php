<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>إعداد خدام 2025</title>
    <link rel="icon" href="https://img.icons8.com/?size=100&id=102454&format=png&color=FFFFFF" type="image/png" />

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />

    <!-- Cairo Font -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet" />

    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet" />

    <!-- Tailwind -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />

    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Cairo', sans-serif !important;
            background: linear-gradient(to bottom right, #e0f2fe, #ede9fe);
        }

        nav {
            font-family: 'Cairo', sans-serif !important;
            background: linear-gradient(90deg, #93c5fd, #8b5cf6);
            color: white;
            box-shadow: 0 4px 8px rgb(139 92 246 / 0.3);
            padding: 1rem;
        }

        nav a, nav span {
            color: white !important;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.02em;
        }

        nav a:hover, nav button:hover {
            text-decoration: underline;
        }

        nav button {
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            color: white;
        }

        nav .logo {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        main > h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #8b5cf6;
            letter-spacing: 0.03em;
        }

        main > h1 + section {
            margin-top: 2rem;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col">
    <!-- Navigation -->
    <nav class="d-flex justify-content-between align-items-center">
        <a href="{{ url('/dashboard') }}" class="logo">
            إعداد خدام 2025
        </a>

        <div class="d-flex gap-4 align-items-center">
            @guest
                <a href="{{ route('login') }}">تسجيل الدخول</a>
                <a href="{{ route('register') }}">إنشاء حساب</a>
            @else
                <span>{{ Auth::user()->first_name }}</span>
                <a href="{{ route('profile') }}">الملف الشخصي</a>

                @if(Auth::user()->roles->contains('role_name', 'Admin'))
                    <a href="{{ route('admin.users.unverified') }}">لوحة التحكم</a>
                @endif

                <form action="{{ route('logout') }}" method="POST" class="m-0 p-0 d-inline">
                    @csrf
                    <button type="submit">تسجيل الخروج</button>
                </form>
            @endguest
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow p-5">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
