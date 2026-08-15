<div class="row g-3 mb-4" wire:poll.30s>
    @foreach([
        ['Revenue today', '₦'.number_format($stats['revenue_today'] / 100, 0), 'cash-stack', 'success'],
        ['Active sessions', number_format($stats['active_sessions']), 'broadcast', 'primary'],
        ['Online routers', number_format($stats['online_routers']), 'router', 'info'],
        ['Available vouchers', number_format($stats['available_vouchers']), 'ticket-perforated', 'warning'],
    ] as [$label, $value, $icon, $color])
        <div class="col-sm-6 col-xl-3">
            <div class="card metric-card h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><p class="text-secondary mb-1">{{ $label }}</p><div class="fs-3 fw-bold">{{ $value }}</div></div>
                    <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} p-3"><i class="bi bi-{{ $icon }} fs-4"></i></span>
                </div>
            </div>
        </div>
    @endforeach
</div>