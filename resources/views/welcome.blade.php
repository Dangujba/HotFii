<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HotFii · Network access, made accountable</title>
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

{{-- ─── Top Navbar ─── --}}
<nav class="navbar navbar-expand-lg border-bottom sticky-top" style="background: var(--hf-header-bg);">
    <div class="container py-2">
        <a class="navbar-brand fw-bold" href="#" style="color: var(--hf-ink);">
            <span class="brand-mark me-2">H</span>HotFii
        </a>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <button class="btn btn-link border-0 p-1" id="theme-toggle" title="Switch theme" style="color: var(--hf-toggle-icon-color); font-size: 1.15rem;">
                <i class="bi bi-sun"></i>
            </button>
            <a class="btn btn-outline-success" href="{{ route('login') }}">Sign in</a>
            <a class="btn btn-hotfii" href="{{ route('register') }}">Start testing free</a>
        </div>
    </div>
</nav>

{{-- ─── Hero ─── --}}
<header class="py-5">
    <div class="container py-lg-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="badge rounded-pill text-bg-warning mb-3">Built for Nigerian network operators</span>
                <h1 class="display-3 fw-bold lh-1">Run paid Wi-Fi and managed access from one place.</h1>
                <p class="lead text-secondary my-4">HotFii connects multi-vendor routers to cloud RADIUS, vouchers, Paystack payments, live sessions and accountable access policies.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="btn btn-hotfii btn-lg">Create free sandbox</a>
                    <a href="#pricing" class="btn btn-outline-secondary btn-lg">See pricing</a>
                </div>
                <p class="small text-secondary mt-3"><i class="bi bi-check2-circle me-1"></i>No admin approval for registration or MikroTik testing.</p>
            </div>
            <div class="col-lg-5">
                <div class="card metric-card p-4" style="background: var(--hf-surface-raised) !important; border-left-color: var(--hf-accent) !important;">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-secondary mb-1">Network command centre</p>
                            <h2 class="h4">All locations online</h2>
                        </div>
                        <i class="bi bi-router display-5" style="color: var(--hf-accent);"></i>
                    </div>
                    <hr style="border-color: var(--hf-border);">
                    <div class="row text-center">
                        <div class="col">
                            <div class="fs-3 fw-bold">12</div>
                            <small class="text-secondary">Routers</small>
                        </div>
                        <div class="col">
                            <div class="fs-3 fw-bold">386</div>
                            <small class="text-secondary">Sessions</small>
                        </div>
                        <div class="col">
                            <div class="fs-3 fw-bold">₦184k</div>
                            <small class="text-secondary">Today</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- ─── Features ─── --}}
<section class="py-5" style="background: var(--hf-surface-raised);">
    <div class="container">
        <div class="row g-4">
            @foreach([
                ['router', 'Multi-vendor', 'MikroTik first, with Generic RADIUS compatibility for UniFi, Omada, Ruijie, Cambium, Cisco, Huawei and D-Link.'],
                ['ticket-perforated', 'Vouchers & payments', 'Printed QR vouchers, agents, cash sales and Paystack split settlement.'],
                ['building-lock', 'Organization control', 'Departments, guest passes, schedules, usage limits and live disconnect for offices and schools.'],
            ] as [$icon, $title, $copy])
                <div class="col-md-4">
                    <div class="card h-100 metric-card">
                        <div class="card-body p-4">
                            <i class="bi bi-{{ $icon }} fs-2 text-hotfii"></i>
                            <h3 class="h5 fw-bold mt-3">{{ $title }}</h3>
                            <p class="text-secondary mb-0">{{ $copy }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ─── Pricing ─── --}}
<section id="pricing" class="py-5">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Pricing that matches how you operate</h2>
            <p class="text-secondary">Sellers pay as they earn. Organizations pay by active identities and sites.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-5">
                <div class="card h-100 metric-card" style="border-left-color: #20c997 !important;">
                    <div class="card-body p-4">
                        <span class="badge text-bg-success">Commerce</span>
                        <h3 class="mt-3">2% of billable sales</h3>
                        <p class="text-secondary">Micro sellers have no minimum up to ₦50,000 monthly sales. Standard is 2% or ₦2,500, whichever is higher.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100 metric-card">
                    <div class="card-body p-4">
                        <span class="badge" style="background: var(--hf-surface-raised); color: var(--hf-ink);">Organizations</span>
                        <h3 class="mt-3">From ₦5,000/month</h3>
                        <p class="text-secondary">Fixed plans for offices, schools and institutions. Routers and access points are not the billing unit.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─── Footer ─── --}}
<footer class="py-4" style="background: var(--hf-footer-bg); border-top: 1px solid var(--hf-border-subtle);">
    <div class="container d-flex justify-content-between">
        <strong style="color: var(--hf-ink);">HotFii</strong>
        <span style="color: var(--hf-ink-secondary);">Network access, made accountable.</span>
    </div>
</footer>

</body>
</html>