<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Services\Billing\OrganizationFinanceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileFinanceController extends Controller
{
    private const ENTRY_STATUSES = ['accrued', 'collected'];

    private const INVOICE_STATUSES = ['draft', 'open', 'paid'];

    public function index(
        Request $request,
        Organization $organization,
        OrganizationFinanceService $finance,
    ): JsonResponse {
        $this->authorizeRead($request, $organization);
        $data = $request->validate([
            'ledger_status' => ['nullable', Rule::in(self::ENTRY_STATUSES)],
            'period' => ['nullable', 'date_format:Y-m'],
            'invoice_status' => ['nullable', Rule::in(self::INVOICE_STATUSES)],
            'router' => ['nullable', 'uuid'],
            'ledger_page' => ['nullable', 'integer', 'min:1'],
            'invoice_page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $router = isset($data['router'])
            ? $organization->networkDevices()->where('uuid', $data['router'])->firstOrFail()
            : null;
        $perPage = (int) ($data['per_page'] ?? 20);
        $entries = FeeLedgerEntry::where('organization_id', $organization->id)
            ->with('networkDevice')
            ->when($data['ledger_status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($data['period'] ?? null, fn (Builder $query, string $month) => $query->whereBetween('billing_period', [
                Carbon::parse($month.'-01')->startOfMonth()->toDateString(),
                Carbon::parse($month.'-01')->endOfMonth()->toDateString(),
            ]))
            ->when($router, fn (Builder $query) => $query->where('network_device_id', $router->id))
            ->latest()
            ->paginate($perPage, ['*'], 'ledger_page');
        $invoices = Invoice::where('organization_id', $organization->id)
            ->when($data['invoice_status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage, ['*'], 'invoice_page');
        $subscription = $organization->subscriptions()->latest()->first();

        return response()->json(['data' => [
            'current' => $finance->current($organization, $router),
            'plan' => [
                'code' => $organization->billing_plan->value,
                'subscription_status' => $subscription?->status,
            ],
            'ledger' => collect($entries->items())->map(fn (FeeLedgerEntry $entry) => [
                'id' => $entry->uuid,
                'billing_period' => $entry->billing_period->format('Y-m'),
                'router_id' => $entry->networkDevice?->uuid,
                'router_name' => $entry->networkDevice?->name,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'billable_sales_kobo' => (int) $entry->billable_sales_kobo,
                'fee_amount_kobo' => (int) $entry->fee_amount_kobo,
                'status' => $entry->status,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values(),
            'ledger_pagination' => $this->pagination($entries),
            'invoices' => collect($invoices->items())->map(fn (Invoice $invoice) => $this->invoice($invoice))->values(),
            'invoice_pagination' => $this->pagination($invoices),
            'options' => [
                'ledger_statuses' => self::ENTRY_STATUSES,
                'invoice_statuses' => self::INVOICE_STATUSES,
                'routers' => $organization->networkDevices()->orderBy('name')->get()->map(fn (NetworkDevice $device) => [
                    'id' => $device->uuid,
                    'name' => $device->name,
                ])->values(),
            ],
            'permissions' => [
                'can_pay_invoices' => $this->canPay($request, $organization),
            ],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, Organization $organization, Invoice $invoice): JsonResponse
    {
        $this->authorizeRead($request, $organization);
        abort_unless($invoice->organization_id === $organization->id, 404);

        return response()->json(['data' => [
            'invoice' => $this->invoice($invoice),
            'permissions' => ['can_pay' => $this->canPay($request, $organization) && ! $invoice->isPaid()],
        ]])->header('Cache-Control', 'no-store, private');
    }

    private function invoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->uuid,
            'number' => $invoice->number,
            'billing_period' => $invoice->billing_period->format('Y-m'),
            'subtotal_kobo' => (int) $invoice->subtotal_kobo,
            'total_kobo' => (int) $invoice->total_kobo,
            'status' => $invoice->status,
            'is_overdue' => $invoice->isOverdue(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'payment_method' => $invoice->payment_method,
            'created_at' => $invoice->created_at?->toIso8601String(),
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

    private function authorizeRead(Request $request, Organization $organization): void
    {
        abort_unless(
            in_array($request->user()->roleFor($organization), ['owner', 'manager', 'accountant', 'viewer'], true),
            403,
            'Your role cannot view organization finance.',
        );
    }

    private function canPay(Request $request, Organization $organization): bool
    {
        return in_array($request->user()->roleFor($organization), ['owner', 'manager'], true);
    }
}
