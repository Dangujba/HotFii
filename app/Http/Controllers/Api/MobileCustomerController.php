<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileCustomerController extends Controller
{
    private const TYPES = ['customer', 'employee', 'student', 'contractor', 'guest'];

    private const STATUSES = ['active', 'suspended', 'expired'];

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $customers = $organization->customers()
            ->with('currentCredential.accessPlan')
            ->withCount(['sessions', 'transactions'])
            ->when(trim((string) ($data['search'] ?? '')), fn (Builder $query, string $term) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->when($data['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($data['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 20));

        return response()->json(['data' => [
            'customers' => collect($customers->items())->map(fn (Customer $customer) => $this->customer($customer))->values(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
            'options' => ['types' => self::TYPES, 'statuses' => self::STATUSES],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, Organization $organization, Customer $customer): JsonResponse
    {
        abort_unless($customer->organization_id === $organization->id, 404);
        $customer->load('currentCredential.accessPlan');
        $customer->loadCount(['sessions', 'credentials', 'transactions', 'vouchers']);
        $sessions = $customer->sessions()->with('networkDevice', 'accessPlan')->latest('started_at')->limit(10)->get();
        $transactions = $customer->transactions()->with('networkDevice', 'accessPlan')->latest()->limit(10)->get();

        return response()->json(['data' => [
            'customer' => $this->customer($customer) + [
                'email' => $customer->email,
                'expires_at' => $customer->expires_at?->toIso8601String(),
                'last_authenticated_at' => $customer->last_authenticated_at?->toIso8601String(),
                'credentials_count' => (int) $customer->credentials_count,
                'vouchers_count' => (int) $customer->vouchers_count,
            ],
            'recent_sessions' => $sessions->map(fn ($session) => [
                'id' => $session->uuid,
                'router_name' => $session->networkDevice?->name,
                'plan_name' => $session->accessPlan?->name,
                'status' => $session->status,
                'started_at' => $session->started_at?->toIso8601String(),
                'stopped_at' => $session->stopped_at?->toIso8601String(),
                'total_bytes' => (int) $session->totalBytes(),
            ])->values(),
            'recent_transactions' => $transactions->map(fn ($transaction) => [
                'id' => $transaction->uuid,
                'reference' => $transaction->reference,
                'sale_type' => str_starts_with($transaction->reference, 'HF-VCH-') ? 'voucher' : ($transaction->channel === 'cash' ? 'cash' : 'online'),
                'status' => $transaction->status->value,
                'gross_amount_kobo' => (int) $transaction->gross_amount_kobo,
                'plan_name' => $transaction->accessPlan?->name,
                'router_name' => $transaction->networkDevice?->name,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ])->values(),
        ]])->header('Cache-Control', 'no-store, private');
    }

    private function customer(Customer $customer): array
    {
        return [
            'id' => $customer->uuid,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'type' => $customer->type,
            'status' => $customer->status,
            'current_plan' => $customer->currentCredential?->accessPlan?->name,
            'credential_status' => $customer->currentCredential?->status,
            'sessions_count' => (int) ($customer->sessions_count ?? $customer->sessions()->count()),
            'transactions_count' => (int) ($customer->transactions_count ?? $customer->transactions()->count()),
            'created_at' => $customer->created_at?->toIso8601String(),
        ];
    }
}
