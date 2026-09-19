<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\PlanValidityMode;
use App\Http\Controllers\Controller;
use App\Models\AccessPlan;
use App\Models\Organization;
use App\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccessPlanController extends Controller
{
    private const TYPES = ['paid', 'free', 'internal'];

    public function index(Request $request, Organization $organization): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'type' => ListFilters::choice($request, 'type', self::TYPES),
            'state' => ListFilters::choice($request, 'state', ['active', 'inactive']),
        ];

        return view('operator.plans', [
            'plans' => $organization->accessPlans()
                ->withCount(['voucherBatches', 'transactions', 'accessCredentials', 'sessions'])
                ->when($filters['search'], fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
                ->when($filters['type'], fn ($query, $type) => $query->where('access_type', $type))
                ->when($filters['state'], fn ($query, $state) => $query->where('is_active', $state === 'active'))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'types' => self::TYPES,
            'validityModes' => PlanValidityMode::cases(),
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
            'canManagePlans' => $request->user()->is_platform_admin
                || in_array($request->user()->roleFor($organization), ['owner', 'manager'], true),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        // A paid plan priced at 0 is a contradiction that costs money: every
        // voucher generated from it snapshots 0, and VoucherService then skips
        // the sales counters and the fee ledger entirely. Free and internal
        // plans are the legitimate home for 0, so the floor is conditional.
        $data = $this->validatedPlan($request, $organization);
        $organization->accessPlans()->create($this->attributes($data));

        return back()->with('success', 'Access plan created.');
    }

    public function update(Request $request, Organization $organization, AccessPlan $plan): RedirectResponse
    {
        $this->guardPlan($organization, $plan);
        $data = $this->validatedPlan($request, $organization, $plan);
        $attributes = $this->attributes($data) + ['is_active' => $request->boolean('is_active')];

        DB::transaction(function () use ($organization, $plan, $attributes): void {
            $plan = $organization->accessPlans()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if ($this->hasUsage($plan)) {
                $protected = [
                    'access_type',
                    'duration_minutes',
                    'data_limit_bytes',
                    'download_kbps',
                    'upload_kbps',
                    'simultaneous_use',
                    'validity_days',
                    'validity_mode',
                ];

                foreach ($protected as $field) {
                    $current = $plan->getRawOriginal($field);
                    $next = $attributes[$field] instanceof \BackedEnum
                        ? $attributes[$field]->value
                        : $attributes[$field];

                    if ((string) ($current ?? '') !== (string) ($next ?? '')) {
                        throw ValidationException::withMessages([
                            'plan' => 'This plan has already been issued or sold. Its type and access limits are locked to protect existing customers. You can still change its name, future price, or active status.',
                        ]);
                    }
                }
            }

            $plan->update($attributes);
        });

        return back()->with('success', 'Access plan updated.');
    }

    public function destroy(Organization $organization, AccessPlan $plan): RedirectResponse
    {
        $this->guardPlan($organization, $plan);

        DB::transaction(function () use ($organization, $plan): void {
            $plan = $organization->accessPlans()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if ($this->hasUsage($plan)) {
                throw ValidationException::withMessages([
                    'plan' => 'This plan is already linked to vouchers, sales, or access records and cannot be deleted. Edit it and switch off Active instead.',
                ]);
            }

            $plan->delete();
        });

        return back()->with('success', 'Unused access plan deleted.');
    }

    private function validatedPlan(Request $request, Organization $organization, ?AccessPlan $plan = null): array
    {
        $name = Rule::unique('access_plans', 'name')
            ->where(fn ($query) => $query->where('organization_id', $organization->id));

        if ($plan) {
            $name->ignore($plan->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $name],
            'access_type' => ['required', Rule::in(self::TYPES)],
            'price_naira' => ['required', 'numeric', 'decimal:0,2', Rule::when($request->input('access_type') === 'paid', ['min:1'], ['min:0'])],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'data_limit_mb' => ['nullable', 'integer', 'min:1'],
            'download_kbps' => ['nullable', 'integer', 'min:64'],
            'upload_kbps' => ['nullable', 'integer', 'min:64'],
            'simultaneous_use' => ['required', 'integer', 'min:1', 'max:20'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'validity_mode' => ['nullable', Rule::enum(PlanValidityMode::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'access_type' => $data['access_type'],
            'price_kobo' => (int) round($data['price_naira'] * 100),
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'data_limit_bytes' => isset($data['data_limit_mb']) ? $data['data_limit_mb'] * 1024 * 1024 : null,
            'download_kbps' => $data['download_kbps'] ?? null,
            'upload_kbps' => $data['upload_kbps'] ?? null,
            'simultaneous_use' => $data['simultaneous_use'],
            'validity_days' => $data['validity_days'] ?? null,
            'validity_mode' => $data['validity_mode'] ?? PlanValidityMode::Midnight,
        ];
    }

    private function hasUsage(AccessPlan $plan): bool
    {
        return $plan->voucherBatches()->exists()
            || $plan->transactions()->exists()
            || $plan->accessCredentials()->exists()
            || $plan->sessions()->exists();
    }

    private function guardPlan(Organization $organization, AccessPlan $plan): void
    {
        abort_unless($plan->organization_id === $organization->id, 404);
    }
}
