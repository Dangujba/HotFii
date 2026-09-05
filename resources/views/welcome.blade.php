<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HotFii · Cloud-managed Wi-Fi access for Nigerian network operators. Multi-vendor RADIUS, Paystack payments, vouchers and live session monitoring in one dashboard.">
    <title>HotFii · Network access, made accountable</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
    <style>
        /* ── Landing page font override ── */
        body { font-family: 'Inter', sans-serif; }

        /* ── Navbar glass effect ── */
        .lp-nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: rgba(255,255,255,0.75);
            border-bottom: 1px solid rgba(0,0,0,0.07);
            transition: background 0.3s;
        }
        [data-hotfii-theme="dusk"] .lp-nav,
        [data-hotfii-theme="midnight"] .lp-nav {
            background: rgba(20,16,12,0.80);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .lp-nav .nav-link-pill {
            color: var(--hf-ink-secondary);
            font-size: .875rem;
            font-weight: 500;
            padding: .4rem .75rem;
            border-radius: .5rem;
            text-decoration: none;
            transition: color .2s, background .2s;
        }
        .lp-nav .nav-link-pill:hover { color: var(--hf-primary); background: var(--hf-primary-soft); }

        /* ── Hero ── */
        .hero-section {
            min-height: 92vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            background: var(--hf-surface);
        }
        .hero-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 60% -10%, rgba(244,97,10,.12) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 100% 80%, rgba(244,185,66,.07) 0%, transparent 55%);
            pointer-events: none;
        }
        .hero-title {
            font-size: clamp(2.5rem, 6vw, 4.2rem);
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: -0.03em;
            color: var(--hf-ink);
            margin-bottom: 1.5rem;
        }
        .hero-title .highlight {
            background: linear-gradient(135deg, var(--hf-primary) 0%, var(--hf-accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-sub {
            font-size: 1.15rem;
            color: var(--hf-ink-secondary);
            line-height: 1.7;
            max-width: 520px;
            margin-bottom: 2.5rem;
        }
        .btn-lp-primary {
            background: var(--hf-primary);
            color: #fff;
            border: none;
            border-radius: .75rem;
            padding: .85rem 2rem;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            box-shadow: 0 6px 20px rgba(244,97,10,.3);
            transition: transform .15s, box-shadow .15s, background .15s;
        }
        .btn-lp-primary:hover {
            background: var(--hf-primary-hover);
            box-shadow: 0 8px 28px rgba(244,97,10,.4);
            transform: translateY(-2px);
            color: #fff;
        }
        .btn-lp-ghost {
            background: transparent;
            color: var(--hf-ink);
            border: 1.5px solid var(--hf-border);
            border-radius: .75rem;
            padding: .85rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            transition: border-color .2s, color .2s, background .2s;
        }
        .btn-lp-ghost:hover { border-color: var(--hf-primary); color: var(--hf-primary); background: var(--hf-primary-soft); }

        /* ── Dashboard mock card ── */
        .dashboard-mock {
            background: var(--hf-surface-card);
            border: 1px solid var(--hf-border-subtle);
            border-radius: 1.5rem;
            box-shadow: 0 32px 80px rgba(0,0,0,.14), 0 0 0 1px rgba(244,97,10,.06);
            overflow: hidden;
        }
        .mock-topbar {
            background: var(--hf-surface-raised);
            border-bottom: 1px solid var(--hf-border-subtle);
            padding: .75rem 1.25rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .mock-dot { width: .6rem; height: .6rem; border-radius: 50%; }
        .mock-title { font-size: .75rem; font-weight: 600; color: var(--hf-ink-secondary); margin-left: .5rem; }
        .mock-body { padding: 1.25rem; }
        .mock-stat {
            background: var(--hf-surface-raised);
            border-radius: .85rem;
            padding: .85rem 1rem;
            border-left: 3px solid var(--hf-primary);
        }
        .mock-stat.amber { border-left-color: var(--hf-accent); }
        .mock-stat.success { border-left-color: #20c997; }
        .mock-stat.info { border-left-color: #0dcaf0; }
        .mock-stat-label { font-size: .7rem; font-weight: 600; color: var(--hf-ink-secondary); text-transform: uppercase; letter-spacing: .04em; }
        .mock-stat-value { font-size: 1.4rem; font-weight: 800; color: var(--hf-ink); line-height: 1.2; }
        .mock-device-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .65rem 0;
            border-bottom: 1px solid var(--hf-border-subtle);
        }
        .mock-device-row:last-child { border-bottom: none; }
        .mock-device-icon {
            width: 2rem; height: 2rem;
            border-radius: .5rem;
            background: var(--hf-primary-soft);
            color: var(--hf-primary);
            display: grid;
            place-items: center;
            font-size: .85rem;
            flex-shrink: 0;
        }
        .mock-device-name { font-size: .8rem; font-weight: 600; color: var(--hf-ink); }
        .mock-device-sub { font-size: .68rem; color: var(--hf-ink-secondary); }
        .mock-badge {
            margin-left: auto;
            font-size: .65rem;
            font-weight: 700;
            padding: .2rem .55rem;
            border-radius: 2rem;
            flex-shrink: 0;
        }
        .mock-badge.online { background: rgba(32,201,151,.12); color: #20c997; }
        .mock-badge.warning { background: rgba(244,185,66,.12); color: #d4900a; }

        /* ── Trust bar ── */
        .trust-bar {
            border-top: 1px solid var(--hf-border-subtle);
            border-bottom: 1px solid var(--hf-border-subtle);
            background: var(--hf-surface-raised);
            padding: 1.1rem 0;
            overflow: hidden;
        }
        .trust-bar-inner {
            display: flex;
            align-items: center;
            gap: 2.5rem;
            white-space: nowrap;
        }
        .trust-item {
            display: flex;
            align-items: center;
            gap: .5rem;
            color: var(--hf-ink-secondary);
            font-size: .85rem;
            font-weight: 500;
        }
        .trust-item i { color: var(--hf-primary); }

        /* ── Section labels ── */
        .section-eyebrow {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--hf-primary);
            margin-bottom: .75rem;
        }
        .section-title {
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.025em;
            color: var(--hf-ink);
        }
        .section-sub {
            font-size: 1.05rem;
            color: var(--hf-ink-secondary);
            line-height: 1.7;
            max-width: 560px;
            margin: 0 auto;
        }

        /* ── Feature bento grid ── */
        .bento-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; grid-template-rows: auto auto; gap: 1.25rem; }
        @media (max-width: 991px) { .bento-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 599px) { .bento-grid { grid-template-columns: 1fr; } }
        .bento-card {
            background: var(--hf-surface-card);
            border: 1px solid var(--hf-border-subtle);
            border-radius: 1.25rem;
            padding: 2rem;
            transition: box-shadow .25s, transform .25s;
            position: relative;
            overflow: hidden;
        }
        .bento-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--hf-primary), var(--hf-accent));
            opacity: 0;
            transition: opacity .25s;
        }
        .bento-card:hover { box-shadow: 0 16px 48px rgba(244,97,10,.1); transform: translateY(-3px); }
        .bento-card:hover::before { opacity: 1; }
        .bento-card.span-2 { grid-column: span 2; }
        @media (max-width: 599px) { .bento-card.span-2 { grid-column: span 1; } }
        .bento-icon {
            width: 3rem; height: 3rem;
            border-radius: .85rem;
            background: var(--hf-primary-soft);
            color: var(--hf-primary);
            display: grid;
            place-items: center;
            font-size: 1.3rem;
            margin-bottom: 1.25rem;
        }
        .bento-icon.amber { background: var(--hf-accent-soft); color: #c07a00; }
        .bento-icon.info { background: rgba(13,202,240,.1); color: #0aa2c0; }
        .bento-title { font-size: 1.05rem; font-weight: 700; color: var(--hf-ink); margin-bottom: .5rem; }
        .bento-body { font-size: .9rem; color: var(--hf-ink-secondary); line-height: 1.65; }

        /* Vendor chip list */
        .vendor-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: 1rem; }
        .vendor-chip {
            background: var(--hf-surface-raised);
            border: 1px solid var(--hf-border);
            border-radius: .45rem;
            padding: .2rem .65rem;
            font-size: .7rem;
            font-weight: 600;
            color: var(--hf-ink-secondary);
        }

        /* ── How it works ── */
        .step-card {
            background: var(--hf-surface-card);
            border: 1px solid var(--hf-border-subtle);
            border-radius: 1.25rem;
            padding: 1.75rem;
            position: relative;
        }
        .step-number {
            width: 2.5rem; height: 2.5rem;
            border-radius: 50%;
            background: var(--hf-primary);
            color: #fff;
            font-weight: 800;
            font-size: 1rem;
            display: grid;
            place-items: center;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(244,97,10,.3);
        }
        .step-connector {
            position: absolute;
            top: 2.4rem;
            right: -1.625rem;
            width: 2rem;
            height: 1px;
            border-top: 2px dashed var(--hf-border);
        }
        @media (max-width: 767px) { .step-connector { display: none; } }

        /* ── Stats strip ── */
        .stats-strip {
            background: linear-gradient(135deg, var(--hf-primary) 0%, #d94000 100%);
            padding: 4rem 0;
            position: relative;
            overflow: hidden;
        }
        .stats-strip::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 60% 80% at 80% 50%, rgba(244,185,66,.15) 0%, transparent 60%);
        }
        .stat-item { text-align: center; position: relative; }
        .stat-number { font-size: clamp(2rem, 4vw, 3rem); font-weight: 900; color: #fff; letter-spacing: -0.03em; line-height: 1; }
        .stat-label { font-size: .85rem; font-weight: 500; color: rgba(255,255,255,.7); margin-top: .35rem; }

        /* ── Pricing ── */
        .pricing-card {
            background: var(--hf-surface-card);
            border: 1.5px solid var(--hf-border);
            border-radius: 1.5rem;
            padding: 2.25rem;
            height: 100%;
            transition: box-shadow .25s, border-color .25s;
        }
        .pricing-card:hover { box-shadow: 0 20px 60px rgba(244,97,10,.1); border-color: var(--hf-primary); }
        .pricing-card.featured {
            border-color: var(--hf-primary);
            background: linear-gradient(160deg, var(--hf-surface-card) 60%, var(--hf-primary-soft));
            box-shadow: 0 12px 48px rgba(244,97,10,.15);
        }
        .pricing-tag {
            display: inline-block;
            background: var(--hf-primary);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: .25rem .75rem;
            border-radius: 2rem;
            margin-bottom: 1.25rem;
        }
        .pricing-tag.secondary {
            background: var(--hf-surface-raised);
            color: var(--hf-ink-secondary);
            border: 1px solid var(--hf-border);
        }
        .pricing-price { font-size: 2.2rem; font-weight: 900; color: var(--hf-ink); letter-spacing: -0.04em; line-height: 1; }
        .pricing-period { font-size: .85rem; color: var(--hf-ink-secondary); font-weight: 400; }
        .pricing-desc { font-size: .9rem; color: var(--hf-ink-secondary); line-height: 1.65; margin: 1rem 0 1.5rem; }
        .pricing-feature {
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            font-size: .875rem;
            color: var(--hf-ink);
            margin-bottom: .75rem;
        }
        .pricing-feature i { color: var(--hf-primary); margin-top: .1rem; flex-shrink: 0; }

        /* ── CTA banner ── */
        .cta-banner {
            background: var(--hf-surface-raised);
            border: 1px solid var(--hf-border-subtle);
            border-radius: 1.75rem;
            padding: 4rem 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cta-banner::before {
            content: '';
            position: absolute;
            top: -60px; left: 50%;
            transform: translateX(-50%);
            width: 400px; height: 200px;
            background: radial-gradient(ellipse, rgba(244,97,10,.12) 0%, transparent 70%);
            pointer-events: none;
        }
        .cta-title { font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800; color: var(--hf-ink); letter-spacing: -0.025em; margin-bottom: .75rem; }
        .cta-sub { font-size: 1rem; color: var(--hf-ink-secondary); margin-bottom: 2rem; }

        /* ── Footer ── */
        .lp-footer {
            background: var(--hf-sidebar-bg);
            border-top: 1px solid var(--hf-sidebar-border);
            padding: 3.5rem 0 2rem;
        }
        .footer-brand { font-size: 1.2rem; font-weight: 800; color: #fff; }
        .footer-tagline { font-size: .85rem; color: var(--hf-sidebar-ink); margin-top: .25rem; }
        .footer-link { color: var(--hf-sidebar-ink); font-size: .875rem; text-decoration: none; display: block; margin-bottom: .45rem; transition: color .2s; }
        .footer-link:hover { color: var(--hf-primary); }
        .footer-heading { font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #fff; margin-bottom: 1rem; }
        .footer-divider { border-color: var(--hf-sidebar-border); margin: 2rem 0 1.25rem; }
        .footer-legal { font-size: .8rem; color: var(--hf-sidebar-header); }

        /* ── Scroll animations ── */
        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity .6s ease, transform .6s ease;
        }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-delay-1 { transition-delay: .1s; }
        .reveal-delay-2 { transition-delay: .2s; }
        .reveal-delay-3 { transition-delay: .3s; }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════
     NAVBAR
════════════════════════════════════════ --}}
<nav class="lp-nav">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between py-3">
            <a href="#" class="text-decoration-none d-flex align-items-center gap-2">
                <img src="{{ asset('images/hotfii-icon.png') }}" alt="HotFii Logo" style="height: 34px; width: 34px; border-radius: 8px;">
                <span style="font-weight: 800; font-size: 1.25rem; letter-spacing: -0.02em; color: var(--hf-ink);">HotFii</span>
            </a>
            <div class="d-none d-md-flex align-items-center gap-1">
                <a href="#features" class="nav-link-pill">Features</a>
                <a href="#how" class="nav-link-pill">How it works</a>
                <a href="#pricing" class="nav-link-pill">Pricing</a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="nav-link-pill border-0 bg-transparent" id="theme-toggle" title="Switch theme">
                    <i class="bi bi-sun" style="font-size: 1rem;"></i>
                </button>
                <a href="{{ route('login') }}" class="nav-link-pill">Sign in</a>
                <a href="{{ route('register') }}" class="btn-lp-primary" style="padding: .55rem 1.25rem; font-size: .9rem;">
                    Get started <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

{{-- ═══════════════════════════════════════
     HERO
════════════════════════════════════════ --}}
<section class="hero-section">
    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <h1 class="hero-title">
                    The command centre<br>for <span class="highlight">every network</span><br>you operate.
                </h1>
                <p class="hero-sub">
                    HotFii unifies multi-vendor routers, cloud RADIUS, Paystack payments, QR vouchers and live session control into a single, accountable dashboard.
                </p>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <a href="{{ route('register') }}" class="btn-lp-primary">
                        <i class="bi bi-box-arrow-in-right"></i> Create free sandbox
                    </a>
                    <a href="#pricing" class="btn-lp-ghost">
                        See pricing <i class="bi bi-arrow-down"></i>
                    </a>
                </div>
                <p style="font-size: .82rem; color: var(--hf-ink-secondary);">
                    <i class="bi bi-check-circle text-hotfii me-1"></i>No approval required &nbsp;·&nbsp;
                    <i class="bi bi-check-circle text-hotfii me-1"></i>MikroTik ready in minutes
                </p>
            </div>
            <div class="col-lg-6">
                {{-- Dashboard Mock --}}
                <div class="dashboard-mock">
                    <div class="mock-topbar">
                        <span class="mock-dot" style="background:#ef4444;"></span>
                        <span class="mock-dot" style="background:#f59e0b;"></span>
                        <span class="mock-dot" style="background:#22c55e;"></span>
                        <span class="mock-title">HotFii Dashboard · Gwarinpa HQ</span>
                    </div>
                    <div class="mock-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="mock-stat">
                                    <div class="mock-stat-label">Revenue today</div>
                                    <div class="mock-stat-value">₦184,500</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mock-stat amber">
                                    <div class="mock-stat-label">Active sessions</div>
                                    <div class="mock-stat-value">386</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mock-stat success">
                                    <div class="mock-stat-label">Online routers</div>
                                    <div class="mock-stat-value">12 / 12</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mock-stat info">
                                    <div class="mock-stat-label">Vouchers left</div>
                                    <div class="mock-stat-value">1,240</div>
                                </div>
                            </div>
                        </div>
                        <div style="font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--hf-ink-secondary); margin-bottom:.6rem;">Network health</div>
                        @foreach([['Wuse Market AP', 'MikroTik · hAP ac³', 'online'],['Jabi Lake HQ', 'MikroTik · RB4011', 'online'],['Garki Phase 2', 'UniFi · U6-Pro', 'warning'],] as [$name, $model, $status])
                        <div class="mock-device-row">
                            <div class="mock-device-icon"><i class="bi bi-router"></i></div>
                            <div>
                                <div class="mock-device-name">{{ $name }}</div>
                                <div class="mock-device-sub">{{ $model }}</div>
                            </div>
                            <span class="mock-badge {{ $status }}">{{ $status === 'online' ? '● Online' : '⚠ Syncing' }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════
     TRUST BAR
════════════════════════════════════════ --}}
<div class="trust-bar">
    <div class="container">
        <div class="trust-bar-inner">
            <div class="trust-item"><i class="bi bi-shield-check"></i> Cloud RADIUS &amp; DHCP</div>
            <div class="trust-item"><i class="bi bi-credit-card"></i> Paystack split settlement</div>
            <div class="trust-item"><i class="bi bi-qr-code"></i> Printed QR vouchers</div>
            <div class="trust-item"><i class="bi bi-router"></i> MikroTik · UniFi · Omada · Ruijie</div>
            <div class="trust-item"><i class="bi bi-broadcast"></i> Live session monitoring</div>
            <div class="trust-item"><i class="bi bi-building"></i> Multi-org &amp; team roles</div>
            <div class="trust-item"><i class="bi bi-person-badge"></i> Captive portal builder</div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════
     FEATURES
════════════════════════════════════════ --}}
<section id="features" class="py-6" style="padding: 6rem 0;">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <div class="section-eyebrow">Platform capabilities</div>
            <h2 class="section-title">Everything a network operator needs.</h2>
            <p class="section-sub mt-3">From the router handshake to the customer invoice, HotFii owns the full stack so you do not have to stitch tools together.</p>
        </div>
        <div class="bento-grid">
            <div class="bento-card span-2 reveal reveal-delay-1">
                <div class="bento-icon"><i class="bi bi-router"></i></div>
                <div class="bento-title">Multi-vendor router support</div>
                <div class="bento-body">HotFii speaks RADIUS natively. Connect your gear in minutes with automated provisioning scripts. First-class MikroTik RouterOS support with universal RADIUS fallback for everything else.</div>
                <div class="vendor-chips">
                    <span class="vendor-chip">MikroTik</span>
                    <span class="vendor-chip">UniFi</span>
                    <span class="vendor-chip">Omada</span>
                    <span class="vendor-chip">OpenWrt</span>
                    
                    
                    
                    
                    
                </div>
            </div>
            <div class="bento-card reveal reveal-delay-2">
                <div class="bento-icon amber"><i class="bi bi-ticket-perforated"></i></div>
                <div class="bento-title">Vouchers &amp; agent sales</div>
                <div class="bento-body">Generate printable QR voucher batches. Support walk-in cash sales and track every kobo through agent commissions.</div>
            </div>
            <div class="bento-card reveal reveal-delay-1">
                <div class="bento-icon"><i class="bi bi-credit-card-2-front"></i></div>
                <div class="bento-title">Paystack payments</div>
                <div class="bento-body">Online checkout with automatic split settlement. Collect from customers, settle to sub-accounts with zero manual reconciliation.</div>
            </div>
            <div class="bento-card reveal reveal-delay-2">
                <div class="bento-icon info"><i class="bi bi-broadcast"></i></div>
                <div class="bento-title">Live session control</div>
                <div class="bento-body">See every connected client in real time. Disconnect abusive users, enforce data caps and expire sessions on demand.</div>
            </div>
            <div class="bento-card reveal reveal-delay-3">
                <div class="bento-icon"><i class="bi bi-building-lock"></i></div>
                <div class="bento-title">Organisation control</div>
                <div class="bento-body">Departments, guest passes, scheduled access windows, device limits and usage caps for schools, offices and multi-site businesses.</div>
            </div>
            <div class="bento-card reveal reveal-delay-1">
                <div class="bento-icon amber"><i class="bi bi-people"></i></div>
                <div class="bento-title">Team &amp; role management</div>
                <div class="bento-body">Invite staff, assign scoped roles, and audit every configuration change with a tamper-proof log.</div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════
     STATS
════════════════════════════════════════ --}}
<div class="stats-strip">
    <div class="container">
        <div class="row g-4 justify-content-center text-center">
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number">10+</div>
                    <div class="stat-label">Router vendors supported</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number">< 5 min</div>
                    <div class="stat-label">MikroTik onboarding time</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number">2%</div>
                    <div class="stat-label">Commerce fee · No hidden charges</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Real-time session monitoring</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════
     HOW IT WORKS
════════════════════════════════════════ --}}
<section id="how" style="padding: 6rem 0; background: var(--hf-surface);">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <div class="section-eyebrow">How it works</div>
            <h2 class="section-title">Up and running in four steps.</h2>
        </div>
        <div class="row g-4">
            @foreach([
                ['1','Register & sandbox','Create your free account. No approval, no credit card. Your sandbox is live instantly.',false],
                ['2','Connect your router','Paste the generated RADIUS credentials or run the one-click RouterOS provisioning script.',false],
                ['3','Set plans & policies','Define time, data, and speed plans. Build a captive portal and set access schedules.',false],
                ['4','Start earning','Sell vouchers, accept Paystack payments and monitor every session live.',true],
            ] as [$n, $t, $d, $last])
            <div class="col-md-6 col-lg-3 reveal reveal-delay-{{ $loop->index + 1 <= 3 ? $loop->index + 1 : 3 }}">
                <div class="step-card" style="{{ $loop->last ? 'border-color: var(--hf-primary); background: var(--hf-primary-soft);' : '' }}">
                    @if(!$loop->last)<div class="step-connector"></div>@endif
                    <div class="step-number">{{ $n }}</div>
                    <div style="font-weight: 700; color: var(--hf-ink); margin-bottom: .5rem;">{{ $t }}</div>
                    <div style="font-size: .875rem; color: var(--hf-ink-secondary); line-height: 1.6;">{{ $d }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════
     PRICING
════════════════════════════════════════ --}}
<section id="pricing" style="padding: 6rem 0; background: var(--hf-surface-raised);">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <div class="section-eyebrow">Transparent pricing</div>
            <h2 class="section-title">Pricing that grows with you.</h2>
            <p class="section-sub mt-3">No setup fees. No per-router charges. Pay for what you actually use.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-4 col-md-6 reveal reveal-delay-1">
                <div class="pricing-card">
                    <div class="pricing-tag secondary">Commerce</div>
                    <div class="pricing-price">2%</div>
                    <div class="pricing-period">of billable sales &nbsp;·&nbsp; min ₦2,500/mo after ₦50k</div>
                    <div class="pricing-desc">Perfect for Wi-Fi hotspot sellers, café operators and market vendors monetising internet access.</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Voucher &amp; agent sales</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Paystack online checkout</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Printed QR voucher batches</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Live session monitoring</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Micro seller · No minimum up to ₦50k</div>
                    <div class="mt-4">
                        <a href="{{ route('register') }}" class="btn-lp-ghost" style="justify-content: center; width: 100%;">Start free sandbox</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-2">
                <div class="pricing-card featured">
                    <div class="pricing-tag">Organizations</div>
                    <div class="pricing-price">₦5,000</div>
                    <div class="pricing-period">/ month &nbsp;·&nbsp; plans scale by identities &amp; sites</div>
                    <div class="pricing-desc">For offices, schools and institutions that need scheduled access, department controls and identity management.</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Access groups &amp; schedules</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Per-identity device &amp; data limits</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Guest pass portal</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Team roles &amp; audit log</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Multi-location support</div>
                    <div class="mt-4">
                        <a href="{{ route('register') }}" class="btn-lp-primary" style="justify-content: center; width: 100%;">Start free sandbox <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-3">
                <div class="pricing-card">
                    <div class="pricing-tag secondary">Internal / ISP</div>
                    <div class="pricing-price">Custom</div>
                    <div class="pricing-period">contact for enterprise volume pricing</div>
                    <div class="pricing-desc">For ISPs and infrastructure operators managing hundreds of CPEs and thousands of subscribers.</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>All Organization features</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>Dedicated RADIUS cluster</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>API access &amp; webhooks</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>SLA &amp; priority support</div>
                    <div class="pricing-feature"><i class="bi bi-check-circle-fill"></i>White-label portal option</div>
                    <div class="mt-4">
                        <a href="mailto:hello@hotfii.com" class="btn-lp-ghost" style="justify-content: center; width: 100%;">Contact sales</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════
     CTA
════════════════════════════════════════ --}}
<section style="padding: 6rem 0; background: var(--hf-surface);">
    <div class="container">
        <div class="cta-banner reveal">
            <div class="section-eyebrow" style="justify-content: center; display: inline-block;">Zero commitment sandbox</div>
            <h2 class="cta-title">Ready to take control of your network?</h2>
            <p class="cta-sub">Create your free sandbox in under a minute. No credit card. No approval. MikroTik ready.</p>
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="{{ route('register') }}" class="btn-lp-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Create free sandbox
                </a>
                <a href="{{ route('login') }}" class="btn-lp-ghost">Sign in to existing account</a>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════
     FOOTER
════════════════════════════════════════ --}}
<footer class="lp-footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <img src="{{ asset('images/hotfii-icon.png') }}" alt="HotFii Logo" style="height: 32px; width: 32px; border-radius: 8px;">
                    <span class="footer-brand">HotFii</span>
                </div>
                <div class="footer-tagline">Network access, made accountable.</div>
                <div class="footer-tagline mt-2">Built for Nigerian network operators, from hotspot sellers to institutional ISPs.</div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="footer-heading">Platform</div>
                <a href="#features" class="footer-link">Features</a>
                <a href="#pricing" class="footer-link">Pricing</a>
                <a href="#how" class="footer-link">How it works</a>
                <a href="{{ route('login') }}" class="footer-link">Sign in</a>
            </div>
            <div class="col-6 col-lg-2">
                <div class="footer-heading">Compatibility</div>
                <span class="footer-link">MikroTik RouterOS</span>
                <span class="footer-link">UniFi / Omada</span>
                <span class="footer-link">Ruijie / Cambium</span>
                <span class="footer-link">Generic RADIUS</span>
            </div>
            <div class="col-6 col-lg-2">
                <div class="footer-heading">Integrations</div>
                <span class="footer-link">Paystack</span>
                <span class="footer-link">Cloud RADIUS</span>
                <span class="footer-link">Captive Portal</span>
                <span class="footer-link">QR Vouchers</span>
            </div>
            <div class="col-6 col-lg-2">
                <div class="footer-heading">Legal</div>
                <a href="#" class="footer-link">Privacy Policy</a>
                <a href="#" class="footer-link">Terms of Service</a>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="footer-legal">&copy; {{ date('Y') }} HotFii. All rights reserved.</div>
            <div class="footer-legal">Made with <i class="bi bi-heart-fill" style="color:var(--hf-primary); font-size:.7rem;"></i> in Nigeria</div>
        </div>
    </div>
</footer>

<script>
    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
    }, { threshold: 0.12 });
    reveals.forEach(el => io.observe(el));

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const target = document.querySelector(a.getAttribute('href'));
            if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });
</script>
</body>
</html>