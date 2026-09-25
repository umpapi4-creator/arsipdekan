<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Arsip Prodi')</title>
    <link rel="icon" href="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('favicon.svg'))) }}" type="image/svg+xml">
    <style>{!! file_get_contents(public_path('css/app.css')) !!}</style>
    @stack('head')
</head>
<body>
    @yield('content')
    @stack('scripts')
</body>
</html>
