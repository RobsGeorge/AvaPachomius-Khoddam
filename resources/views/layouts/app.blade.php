<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>إعداد خدام 2025</title>

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

        nav, footer {
            font-family: 'Cairo', sans-serif !important;
            background: linear-gradient(90deg, #93c5fd, #8b5cf6); /* baby blue to violet */
            color: white;
            box-shadow: 0 4px 8px rgb(139 92 246 / 0.3);
        }

        nav a, nav span, footer {
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
            color: #8b5cf6; /* violet */
            letter-spacing: 0.03em;
        }

        main > h1 + section {
            margin-top: 2rem;
        }

        footer {
            font-size: 0.9rem;
            padding: 1rem 0;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col">

    <!-- Navigation -->
    <nav class="p-3 d-flex justify-content-between align-items-center">
    <a href="{{ url('/dashboard') }}" class="logo" style="color: #c084fc; font-weight: 700; font-size: 1.25rem;">
    إعداد خدام 2025
</a>


        <div class="d-flex gap-4 align-items-center">
            @guest
                <a href="{{ route('login') }}" style="color: #6366f1;">تسجيل الدخول</a>
                <a href="{{ route('register') }}" style="color: #8b5cf6;">إنشاء حساب</a>
            @else
                <span style="color: #2563eb;">{{ Auth::user()->first_name }}</span>
                <a href="{{ route('profile') }}" style="color: #7c3aed;">الملف الشخصي</a>

                @if(Auth::user()->roles->contains('role_name', 'Admin'))
                    <a href="{{ route('admin.users.unverified') }}" style="color: #a78bfa;">لوحة التحكم</a>
                @endif

                <form action="{{ route('logout') }}" method="POST" class="m-0 p-0 d-inline">
                    @csrf
                    <button type="submit" style="color: #4f46e5; background: none; border: none; cursor: pointer;">تسجيل الخروج</button>
                </form>
            @endguest
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow p-5 container">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="text-center">
        &copy; {{ date('Y') }} إعداد خدام
    </footer>

    @stack('scripts')
</body>
</html>
