<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Platform') · HotFii</title>
    <link rel="icon" type="image/png" href="{{ asset('images/hotfii-icon.png') }}">
    {{-- Prevent flash of wrong theme before JS loads --}}
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
    @stack('styles')
</head>
{{-- No data-organization-uuid: a support-only admin belongs to no organization,
     so nothing in this shell may read one. That is also why there is no Echo
     channel and no notification bell here. --}}
<body class="layout-fixed sidebar-expand-lg">
@php
    // One indexed count per page load, for the sidebar badge. Cheap: payment
    // profiles are one row per organization.
    $pendingReviews = \App\Models\Organization::whereHas('paymentProfile', fn ($query) => $query->where('status', 'submitted'))->count();
@endphp
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li>
                <li class="nav-item d-none d-md-block"><span class="nav-link"><i class="bi bi-shield-lock me-1"></i>Platform console</span></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <button class="nav-link border-0 bg-transparent" id="theme-toggle" title="Switch theme">
                        <i class="bi bi-sun"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="{{ route('platform.reviews.index') }}" title="Payment reviews">
                        <i class="bi bi-person-check"></i>
                        @if($pendingReviews)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $pendingReviews }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#"><i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }}</a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <span class="dropdown-item-text text-secondary small">{{ auth()->user()->email }}</span>
                        <div class="dropdown-divider"></div>
                        @if(auth()->user()->organizations()->exists())<a class="dropdown-item" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Operator dashboard</a><div class="dropdown-divider"></div>@endif
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar">
        <div class="sidebar-brand"><a href="{{ route('platform.index') }}" class="brand-link d-flex align-items-center"><img src="{{ asset('images/hotfii-icon.png') }}" alt="HotFii Logo" style="height: 28px; width: 28px; border-radius: 7px;" class="me-2"><span class="brand-text fw-bold" style="font-size: 1.15rem; letter-spacing: -0.02em;">HotFii <span class="fw-normal opacity-75">Platform</span></span></a></div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                    <li class="nav-item"><a href="{{ route('platform.index') }}" class="nav-link {{ request()->routeIs('platform.index') ? 'active' : '' }}"><i class="nav-icon bi bi-speedometer2"></i><p>Overview</p></a></li>
                    <li class="nav-header">TENANTS</li>
                    <li class="nav-item"><a href="{{ route('platform.organizations.index') }}" class="nav-link {{ request()->routeIs('platform.organizations.*') ? 'active' : '' }}"><i class="nav-icon bi bi-buildings"></i><p>Organizations</p></a></li>
                    <li class="nav-item"><a href="{{ route('platform.reviews.index') }}" class="nav-link {{ request()->routeIs('platform.reviews.*') ? 'active' : '' }}"><i class="nav-icon bi bi-person-check"></i><p>Payment reviews @if($pendingReviews)<span class="nav-badge badge text-bg-danger">{{ $pendingReviews }}</span>@endif</p></a></li>
                    <li class="nav-header">MONEY</li>
                    <li class="nav-item"><a href="{{ route('platform.billing.index') }}" class="nav-link {{ request()->routeIs('platform.billing.*') ? 'active' : '' }}"><i class="nav-icon bi bi-receipt"></i><p>Billing & Invoices</p></a></li>
                    <li class="nav-item"><a href="{{ route('platform.transactions.index') }}" class="nav-link {{ request()->routeIs('platform.transactions.*') ? 'active' : '' }}"><i class="nav-icon bi bi-cash-stack"></i><p>Transactions</p></a></li>
                    <li class="nav-header">PLATFORM</li>
                    <li class="nav-item"><a href="{{ route('platform.users.index') }}" class="nav-link {{ request()->routeIs('platform.users.*') ? 'active' : '' }}"><i class="nav-icon bi bi-people"></i><p>Users</p></a></li>
                    <li class="nav-item"><a href="{{ route('platform.audit.index') }}" class="nav-link {{ request()->routeIs('platform.audit.*') ? 'active' : '' }}"><i class="nav-icon bi bi-journal-text"></i><p>Audit Log</p></a></li>
                    <li class="nav-item"><a href="{{ route('platform.system.index') }}" class="nav-link {{ request()->routeIs('platform.system.*') ? 'active' : '' }}"><i class="nav-icon bi bi-activity"></i><p>System Health</p></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        @if(session()->has('impersonated_organization_id'))
            {{-- Support mode follows the session, not the page, so it has to be
                 visible here too: an admin can navigate back into the console
                 while still holding a customer's organization. --}}
            <div class="alert alert-danger rounded-0 border-0 mb-0 d-flex justify-content-between align-items-center px-4">
                <span><i class="bi bi-shield-exclamation me-2"></i>Support mode is still open on another organization.</span>
                <form method="POST" action="{{ route('platform.impersonate.stop') }}">@csrf<button class="btn btn-sm btn-light" data-confirm-title="Leave support mode?" data-confirm="Re-entering requires a fresh written reason." data-confirm-icon="info" data-confirm-button="Leave support mode">Exit support mode</button></form>
            </div>
        @endif
        <div class="app-content-header">
            <div class="container-fluid"><div class="row">
                <div class="col-sm-8"><h3 class="mb-0">@yield('heading', 'Platform')</h3><p class="text-secondary mb-0">@yield('subheading')</p></div>
                <div class="col-sm-4 text-sm-end">@yield('actions')</div>
            </div></div>
        </div>
        <div class="app-content"><div class="container-fluid">
            @if(session('success'))<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger"><strong>Please correct the following:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div></div>
    </main>
    <footer class="app-footer"><span class="float-end d-none d-sm-inline">Network access, made accountable.</span><strong>HotFii</strong> · {{ date('Y') }}</footer>
</div>
@stack('scripts')
</body>
</html>
