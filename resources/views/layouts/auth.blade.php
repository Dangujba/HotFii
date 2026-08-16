<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · HotFii</title>
    <link rel="icon" type="image/png" href="{{ asset('images/hotfii-icon.png') }}">
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
<main class="auth-shell d-flex align-items-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-xl-5">
                <div class="text-center text-white mb-4">
                    <a class="text-white text-decoration-none d-inline-flex align-items-center gap-2 fs-2 fw-bold" href="{{ route('home') }}">
                        <img src="{{ asset('images/hotfii-icon.png') }}" alt="HotFii Logo" style="height: 42px; width: 42px; border-radius: 10px;">
                        <span>HotFii</span>
                    </a>
                    <p class="opacity-75 mt-2">One dashboard for every network.</p>
                </div>
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-body p-4 p-md-5">
                        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>