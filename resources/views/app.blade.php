<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0052FF">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <title inertia>{{ config('app.name', 'WaOrder') }}</title>
    @routes
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased bg-background">
    @inertia
</body>
</html>
