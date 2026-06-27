<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - {{ $organizationName }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://challenges.cloudflare.com">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#1e5bc4] font-sans antialiased">
    @php
        $turnstileSiteKey = config('services.turnstile.enabled') ? config('services.turnstile.site_key') : '';
    @endphp
    <div id="login-app" data-page="login" data-props='@json(["organizationName" => $organizationName, "turnstileSiteKey" => $turnstileSiteKey])'></div>
</body>
</html>
