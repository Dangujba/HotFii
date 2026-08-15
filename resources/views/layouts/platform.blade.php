<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Platform') · HotFii</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg bg-dark navbar-dark shadow-sm"><div class="container-fluid px-4">
    <a class="navbar-brand fw-bold" href="{{ route('platform.index') }}"><span class="brand-mark me-2">H</span>HotFii Platform</a>
    <div class="d-flex align-items-center gap-3"><span class="text-white-50">{{ auth()->user()->email }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-light">Sign out</button></form></div>
</div></nav>
<main class="container-fluid px-4 py-4">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
</body>
</html>