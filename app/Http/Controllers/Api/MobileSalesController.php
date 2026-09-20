<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\Sales\DirectCashSaleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileSalesController extends Controller
{
    private const CHANNELS = ['online', 'voucher', 'cash'];

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'channel' => ['nullable', Rule::in(self::CHANNELS)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'router' => ['nullable', 'uuid'],
            'transactions_page' => ['nullable', 'integer', 'min:1'],
            'vouchers_page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $router = isset($data['router'])
            ? $organization->networkDevices()->where('uuid', $data['router'])->firstOrFail()
            : null;
        $perPage = (int) ($data['per_page'] ?? 20);

        $transactions = $this->transactions($organization, $data, $router)
            ->with('customer', 'accessPlan', 'networkDevice')
            ->latest()
            ->paginate($perPage, ['*'], 'transactions_page');
        $vouchers = $this->vouchers($organization, $data, $router)
            ->with('customer', 'batch.accessPlan', 'activatedNetworkDevice')
            ->latest('activated_at')
            ->paginate($perPage, ['*'], 'vouchers_page');

        $summaryTransactions = $organization->transactions()->getQuery()
            ->where('status', PaymentStatus::Successful->value)
            ->when($router, fn (Builder $query) => $query->where('network_device_id', $router->id));
        $this->dateScope($summaryTransactions, $data, 'created_at');
        $summaryVouchers = $organization->vouchers()->getQuery()
            ->whereNotNull('activated_at')
            ->when($router, fn (Builder $query) => $query->where('activated_network_device_id', $router->id));
        $this->dateScope($summaryVouchers, $data, 'activated_at');

        $online = (clone $summaryTransactions)->where('channel', 'online')->sum('gross_amount_kobo');
        $printed = (clone $summaryTransactions)->where('reference', 'like', 'HF-VCH-%')->sum('gross_amount_kobo');
        $direct = (clone $summaryTransactions)
            ->where('channel', 'cash')
            ->where('reference', 'not like', 'HF-VCH-%')
            ->sum('gross_amount_kobo');
        [$canRecordCash, $cashReason] = $this->cashPermission($request, $organization);

        return response()->json(['data' => [
            'summary' => [
                'online_sales_kobo' => (int) $online,
                'printed_voucher_sales_kobo' => (int) $printed,
                'direct_cash_sales_kobo' => (int) $direct,
                'total_sales_kobo' => (int) ($online + $printed + $direct),
                'voucher_activations_count' => (clone $summaryVouchers)->count(),
            ],
            'transactions' => collect($transactions->items())
                ->map(fn (Transaction $transaction) => $this->transaction($transaction))
                ->values(),
            'transactions_pagination' => $this->pagination($transactions),
            'voucher_activations' => collect($vouchers->items())
                ->map(fn (Voucher $voucher) => $this->voucher($voucher))
                ->values(),
            'vouchers_pagination' => $this->pagination($vouchers),
            'options' => [
                'routers' => $organization->networkDevices()
                    ->with('location')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (NetworkDevice $device) => $this->router($device))
                    ->values(),
                'cash_routers' => $organization->networkDevices()
                    ->with('location')
                    ->where('status', 'online')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (NetworkDevice $device) => $this->router($device))
                    ->values(),
                'cash_plans' => $organization->accessPlans()
                    ->where('access_type', 'paid')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($plan) => [
                        'id' => $plan->uuid,
                        'name' => $plan->name,
                        'price_kobo' => (int) $plan->price_kobo,
                    ])
                    ->values(),
                'statuses' => collect(PaymentStatus::cases())
                    ->map(fn (PaymentStatus $status) => $status->value)
                    ->values(),
                'channels' => [
                    ['value' => 'online', 'label' => 'Online'],
                    ['value' => 'voucher', 'label' => 'Printed voucher'],
                    ['value' => 'cash', 'label' => 'Direct cash'],
                ],
            ],
            'permissions' => [
                'can_record_cash' => $canRecordCash,
                'cash_unavailable_reason' => $cashReason,
            ],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Organization $organization, DirectCashSaleService $sales): JsonResponse
    {
        [$canRecordCash, $reason] = $this->cashPermission($request, $organization);
        abort_unless($canRecordCash, 403, $reason ?? 'You cannot record cash sales for this organization.');
        $data = $request->validate([
            'access_plan_id' => ['required', 'uuid'],
            'network_device_id' => ['required', 'uuid'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);
        $plan = $organization->accessPlans()->where('uuid', $data['access_plan_id'])->firstOrFail();
        $device = $organization->networkDevices()->where('uuid', $data['network_device_id'])->firstOrFail();
        $result = $sales->record(
            $organization,
            $plan,
            $device,
            $data['customer_name'] ?? null,
            $data['phone'] ?? null,
        );

        return response()->json([
            'data' => [
                'transaction' => $this->transaction($result['transaction']),
                'credential' => ['username' => $result['username'], 'password' => $result['password']],
            ],
            'message' => 'Direct cash sale recorded and access activated.',
        ], 201)->header('Cache-Control', 'no-store, private');
    }

    private function transactions(Organization $organization, array $data, ?NetworkDevice $router): Builder
    {
        $query = $organization->transactions()->getQuery()
            ->when(trim((string) ($data['search'] ?? '')), fn (Builder $query, string $term) => $query->where(fn (Builder $inner) => $inner
                ->where('reference', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))))
            ->when($data['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($data['channel'] ?? null, function (Builder $query, string $channel) {
                if ($channel === 'voucher') {
                    return $query->where('reference', 'like', 'HF-VCH-%');
                }
                $query->where('channel', $channel);

                return $channel === 'cash'
                    ? $query->where('reference', 'not like', 'HF-VCH-%')
                    : $query;
            })
            ->when($router, fn (Builder $query) => $query->where('network_device_id', $router->id));
        $this->dateScope($query, $data, 'created_at');

        return $query;
    }

    private function vouchers(Organization $organization, array $data, ?NetworkDevice $router): Builder
    {
        $query = $organization->vouchers()->getQuery()
            ->whereNotNull('activated_at')
            ->when(trim((string) ($data['search'] ?? '')), fn (Builder $query, string $term) => $query->where(fn (Builder $inner) => $inner
                ->where('serial_number', 'like', "%{$term}%")
                ->orWhere('code_last_four', 'like', "%{$term}%")
                ->orWhereHas('batch', fn (Builder $batch) => $batch->where('reference', 'like', "%{$term}%"))
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))))
            ->when($router, fn (Builder $query) => $query->where('activated_network_device_id', $router->id));
        $this->dateScope($query, $data, 'activated_at');

        return $query;
    }

    private function dateScope(Builder $query, array $data, string $column): void
    {
        $query->when($data['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate($column, '>=', $from))
            ->when($data['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate($column, '<=', $to));
    }

    private function transaction(Transaction $transaction): array
    {
        $saleType = str_starts_with($transaction->reference, 'HF-VCH-')
            ? 'voucher'
            : ($transaction->channel === 'cash' ? 'cash' : 'online');

        return [
            'id' => $transaction->uuid,
            'reference' => $transaction->reference,
            'sale_type' => $saleType,
            'status' => $transaction->status->value,
            'gross_amount_kobo' => (int) $transaction->gross_amount_kobo,
            'platform_fee_kobo' => (int) $transaction->platform_fee_kobo,
            'router_name' => $transaction->networkDevice?->name,
            'customer_name' => $transaction->customer?->name,
            'customer_contact' => $transaction->customer?->phone ?: $transaction->customer?->email,
            'plan_name' => $transaction->accessPlan?->name,
            'paid_at' => $transaction->paid_at?->toIso8601String(),
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    private function voucher(Voucher $voucher): array
    {
        return [
            'id' => $voucher->uuid,
            'serial_number' => $voucher->serial_number,
            'code_last_four' => $voucher->code_last_four,
            'amount_kobo' => $voucher->is_complimentary ? 0 : (int) $voucher->price_snapshot_kobo,
            'is_complimentary' => (bool) $voucher->is_complimentary,
            'plan_name' => $voucher->batch?->accessPlan?->name,
            'batch_reference' => $voucher->batch?->reference,
            'router_name' => $voucher->activatedNetworkDevice?->name,
            'customer_name' => $voucher->customer?->name,
            'activated_at' => $voucher->activated_at?->toIso8601String(),
            'expires_at' => $voucher->expires_at?->toIso8601String(),
        ];
    }

    private function router(NetworkDevice $device): array
    {
        return [
            'id' => $device->uuid,
            'name' => $device->name,
            'location' => $device->location?->name,
            'status' => $device->status->value,
        ];
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /** @return array{0: bool, 1: ?string} */
    private function cashPermission(Request $request, Organization $organization): array
    {
        $roleAllowed = $request->user()->is_platform_admin
            || in_array($request->user()->roleFor($organization), ['owner', 'manager', 'agent'], true);
        if (! $roleAllowed) {
            return [false, 'Your role cannot record direct cash sales.'];
        }
        if (! $organization->sellsAccess()) {
            return [false, 'This organization does not sell guest access.'];
        }
        if (in_array($organization->status, [OrganizationStatus::Suspended, OrganizationStatus::Grace], true)) {
            return [false, 'New paid activations are unavailable while billing is overdue.'];
        }
        if (! $organization->accessPlans()->where('access_type', 'paid')->where('is_active', true)->exists()) {
            return [false, 'Create an active paid plan before recording direct cash access.'];
        }
        if (! $organization->networkDevices()->where('status', 'online')->exists()) {
            return [false, 'An online, fully tested router is required before recording direct cash access.'];
        }

        return [true, null];
    }
}
