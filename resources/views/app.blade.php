<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">

    {{-- Keep the ERP in light mode regardless of browser/private-tab preference. --}}
    <style>
        html {
            background-color: oklch(1 0 0);
        }
    </style>

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/svg+xml" href="/favicon_v2.svg">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon_v2-96x96.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon_v2-48x48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon_v2-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon_v2-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon_v2-16x16.png">
    <link rel="shortcut icon" href="/favicon_v2.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon_v2.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    @routes
    @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
