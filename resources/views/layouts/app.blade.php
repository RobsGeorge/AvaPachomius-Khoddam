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

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Cairo', sans-serif !important;
            background: linear-gradient(to bottom right, #e0f2fe, #ede9fe);
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

        /* Responsive Navigation */
        @media (max-width: 768px) {
            .navbar-collapse {
                background-color: white;
                padding: 1rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
        }

        /* Responsive Tables */
        @media (max-width: 640px) {
            .table-responsive {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }

        /* Responsive Cards */
        @media (max-width: 768px) {
            .card {
                margin-bottom: 1rem;
            }
        }

        /* Responsive Forms */
        @media (max-width: 640px) {
            .form-group {
                margin-bottom: 1rem;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="min-h-screen flex flex-col" x-data="{ open: false }">
    @include('layouts.navigation')
    <main class="flex-grow container mx-auto px-4 py-8">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
