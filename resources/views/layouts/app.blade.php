<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · HotFii</title>
    <link rel="icon" type="image/png" href="{{ asset('images/hotfii-icon.png') }}">
    {{-- Prevent flash of wrong theme before JS loads --}}
    <script>
        (function(){
            var t = localStorage.getItem('hotfii-theme');
            if (!t || !['daylight','dusk','midnight'].includes(t)) {
                t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'midnight' : 'daylight';
            }
            var bs = t === 'daylight' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-hotfii-theme', t);
            document.documentElement.setAttribute('data-bs-theme', bs);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>
<body class="layout-fixed sidebar-expand-lg" data-organization-uuid="{{ $currentOrganization->uuid }}">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li>
                <li class="nav-item dropdown d-none d-md-block">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">{{ $currentOrganization->name }}</a>
                    @if(! session()->has('impersonated_organization_id') && auth()->user()->organizations()->count() > 1)
                        <div class="dropdown-menu">
                            @foreach(auth()->user()->organizations as $organization)
                                <form method="POST" action="{{ route('organizations.switch', $organization) }}">
                                    @csrf
                                    <button class="dropdown-item {{ $organization->is($currentOrganization) ? 'active' : '' }}">{{ $organization->name }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <button class="nav-link border-0 bg-transparent" id="theme-toggle" title="Switch theme">
                        <i class="bi bi-sun"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="{{ route('notifications.index') }}">
                        <i class="bi bi-bell"></i>
                        @if(auth()->user()->unreadNotifications()->count())
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ auth()->user()->unreadNotifications()->count() }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item"><span class="nav-link"><span class="badge text-bg-{{ in_array($currentOrganization->status->value, ['live','trial'], true) ? 'success' : 'warning' }}">{{ str_replace('_', ' ', ucfirst($currentOrganization->status->value)) }}</span></span></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#"><i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }}</a>
                    <div class="dropdown-menu dropdown-menu-end">
                        @if(auth()->user()->is_platform_admin)<a class="dropdown-item" href="{{ route('platform.index') }}"><i class="bi bi-shield-lock me-2"></i>Platform admin</a><div class="dropdown-divider"></div>@endif
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar">
        <div class="sidebar-brand"><a href="{{ route('dashboard') }}" class="brand-link d-flex align-items-center"><img src="{{ asset('images/hotfii-icon.png') }}" alt="HotFii Logo" style="height: 28px; width: 28px; border-radius: 7px;" class="me-2"><span class="brand-text fw-bold" style="font-size: 1.15rem; letter-spacing: -0.02em;">HotFii</span></a></div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                    <li class="nav-item"><a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="nav-icon bi bi-speedometer2"></i><p>Dashboard</p></a></li>
                    <li class="nav-header">NETWORK</li>
                    <li class="nav-item"><a href="{{ route('network.devices.index') }}" class="nav-link {{ request()->routeIs('network.*') ? 'active' : '' }}"><i class="nav-icon bi bi-router"></i><p>Routers & Controllers</p></a></li>
                    <li class="nav-item"><a href="{{ route('sessions.index') }}" class="nav-link {{ request()->routeIs('sessions.*') ? 'active' : '' }}"><i class="nav-icon bi bi-broadcast"></i><p>Live Sessions</p></a></li>
                    <li class="nav-header">ACCESS</li>
                    <li class="nav-item"><a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}"><i class="nav-icon bi bi-people"></i><p>{{ $currentOrganization->mode->value === 'commerce' ? 'Customers' : 'Identities' }}</p></a></li>
                    @if($currentOrganization->mode->value !== 'commerce')<li class="nav-item"><a href="{{ route('access-groups.index') }}" class="nav-link {{ request()->routeIs('access-groups.*') ? 'active' : '' }}"><i class="nav-icon bi bi-calendar-week"></i><p>Access Groups</p></a></li>@endif
                    <li class="nav-item"><a href="{{ route('plans.index') }}" class="nav-link {{ request()->routeIs('plans.*') ? 'active' : '' }}"><i class="nav-icon bi bi-sliders"></i><p>Plans & Policies</p></a></li>
                    @if($currentOrganization->mode->value !== 'internal')
                        <li class="nav-item"><a href="{{ route('vouchers.index') }}" class="nav-link {{ request()->routeIs('vouchers.*') ? 'active' : '' }}"><i class="nav-icon bi bi-ticket-perforated"></i><p>Vouchers</p></a></li>
                        <li class="nav-item"><a href="{{ route('sales.index') }}" class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}"><i class="nav-icon bi bi-cart-check"></i><p>Sales & Agents</p></a></li>
                    @endif
                    <li class="nav-header">BUSINESS</li>
                    <li class="nav-item"><a href="{{ route('finance.index') }}" class="nav-link {{ request()->routeIs('finance.*') ? 'active' : '' }}"><i class="nav-icon bi bi-wallet2"></i><p>Finance</p></a></li>
                    <li class="nav-item"><a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"><i class="nav-icon bi bi-bar-chart"></i><p>Reports</p></a></li>
                    <li class="nav-item"><a href="{{ route('team.index') }}" class="nav-link {{ request()->routeIs('team.*') ? 'active' : '' }}"><i class="nav-icon bi bi-person-gear"></i><p>Team & Roles</p></a></li>
                    <li class="nav-item"><a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"><i class="nav-icon bi bi-gear"></i><p>Settings</p></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        @if(session()->has('impersonated_organization_id'))
            <div class="alert alert-danger rounded-0 border-0 mb-0 d-flex justify-content-between align-items-center px-4">
                <span><i class="bi bi-shield-exclamation me-2"></i>Support mode: you are viewing {{ $currentOrganization->name }}.</span>
                <form method="POST" action="{{ route('platform.impersonate.stop') }}">@csrf<button class="btn btn-sm btn-light" data-confirm-title="Leave support mode?" data-confirm="You return to your own organization. Re-entering requires a fresh written reason." data-confirm-icon="info" data-confirm-button="Leave support mode">Exit support mode</button></form>
            </div>
        @endif
        <div class="app-content-header">
            <div class="container-fluid"><div class="row">
                <div class="col-sm-8"><h3 class="mb-0">@yield('heading', 'Dashboard')</h3><p class="text-secondary mb-0">@yield('subheading')</p></div>
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
@livewireScripts
@stack('scripts')
</body>
</html>