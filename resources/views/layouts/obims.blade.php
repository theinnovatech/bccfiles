<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'OBIMS' }} - {{ $organizationName }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f0f4fb] font-sans antialiased">
    <div class="min-h-screen">
        <div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>

        @include('partials.sidebar')

        <div class="app-main">
            @include('partials.header')

            <main class="app-content">
                <div
                    id="page-app"
                    data-page="{{ $page }}"
                    data-props='@json($pageProps ?? [])'
                ></div>
            </main>
        </div>
    </div>
</body>
</html>
