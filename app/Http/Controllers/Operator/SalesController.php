<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Transaction;
use App\Services\Sales\DirectCashSaleService;
use App\Support\ListFilters;
use App\Support\OrganizationRouterFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    /** Channels a transaction can be recorded through. */
    private const CHANNELS = ['online', 'voucher', 'cash'];

    public function index(Request $request, Organization $organization): View
    {
        [$routers, $routerId] = OrganizationRouterFilter::resolve($request, $organization);

        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'status' => ListFilters::choice($request, 'status', ListFilters::enumValues(PaymentStatus::class)),
            'channel' => ListFilters::choice($request, 'channel', self::CHANNELS),
            'from' => ListFilters::date($request, 'from'),
            'to' => ListFilters::date($request, 'to'),
            'router' => $routerId,
        ];

        $successfulTransactions = fn () => $organization->transactions()
            ->where('status', 'successful')
            ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router));

        $voucherActivations = fn () => $organization->vouchers()
            ->whereNotNull('activated_at')
            ->when($routerId, fn ($query, $router) => $query->where('activated_network_device_id', $router));

        return view('operator.sales', [
            'transactions' => $organization->transactions()
                ->with('customer', 'accessPlan', 'networkDevice')
                ->when($filters['search'], fn ($query, $term) => $query->where(fn ($inner) => $inner
                    ->where('reference', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%"))))
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['channel'], function ($query, string $channel) {
                    if ($channel === 'voucher') {
                        return $query->where('reference', 'like', 'HF-VCH-%');
                    }

                    $query->where('channel', $channel);

                    return $channel === 'cash'
                        ? $query->where('reference', 'not like', 'HF-VCH-%')
                        : $query;
                })
                ->when($filters['from'], fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
                ->when($filters['to'], fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
                ->when($filters['router'], fn ($query, $router) => $query->where('network_device_id', $router))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            // Its own page name, or paging the voucher list would drag the
            // transaction table along with it.
            'voucherSales' => $organization->vouchers()
                ->with('batch.accessPlan', 'activatedNetworkDevice')
                ->whereNotNull('sold_at')
                ->when($filters['router'], fn ($query, $router) => $query->where('activated_network_device_id', $router))
                ->latest('sold_at')
                ->paginate(12, ['*'], 'vouchers')
                ->withQueryString(),
            'plans' => $organization->accessPlans()->where('access_type', 'paid')->where('is_active', true)->orderBy('name')->get(),
            'devices' => $routers->filter(fn ($router) => $router->status->value === 'online')->values(),
            'routers' => $routers,
            'selectedRouter' => $routers->firstWhere('id', $routerId),
            'statuses' => PaymentStatus::cases(),
            'channels' => self::CHANNELS,
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
            'totals' => [
                'online' => $successfulTransactions()->where('channel', 'online')->sum('gross_amount_kobo'),
                'voucher' => $voucherActivations()->count(),
                'cash' => $successfulTransactions()
                    ->where('channel', 'cash')
                    ->sum('gross_amount_kobo'),
            ],
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        DirectCashSaleService $sales,
    ): RedirectResponse {
        $data = $request->validate([
            'access_plan_id' => ['required', 'integer'],
            'network_device_id' => ['required', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $plan = $organization->accessPlans()->where('access_type', 'paid')->where('is_active', true)->findOrFail($data['access_plan_id']);
        $device = $organization->networkDevices()->where('status', 'online')->findOrFail($data['network_device_id']);

        $result = $sales->record(
            $organization,
            $plan,
            $device,
            $data['customer_name'] ?? null,
            $data['phone'] ?? null,
        );

        return back()->with('success', 'Direct cash sale recorded and RADIUS access activated.')
            ->with('issued_credential', ['username' => $result['username'], 'password' => $result['password']]);
    }
}
