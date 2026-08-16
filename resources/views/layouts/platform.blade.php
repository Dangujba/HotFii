<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Platform') · HotFii</title>
    <script>
        (function(){
            var t = localStorage.getItem('hotfii-theme');
            if (!t || !['daylight','dusk','midnight'].includes(t)) {
                t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'midnight' : 'daylight';
            }
            document.documentElement.setAttribute('data-hotfii-theme', t);
            document.documentElement.setAttribute('data-bs-theme', t === 'daylight' ? 'light' : 'dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<nav class="navbar navbar-expand-lg shadow-sm" style="background: var(--hf-header-bg); border-bottom: 1px solid var(--hf-header-border);">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="{{ route('platform.index') }}" style="color: var(--hf-ink);"><span class="brand-mark me-2">H</span>HotFii Platform</a>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link border-0 p-1" id="theme-toggle" title="Switch theme" style="color: var(--hf-toggle-icon-color); font-size: 1.15rem;"><i class="bi bi-sun"></i></button>
            <span style="color: var(--hf-ink-secondary);">{{ auth()->user()->email }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Sign out</button></form>
        </div>
    </div>
</nav>
<main class="container-fluid px-4 py-4">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
</body>
</html>