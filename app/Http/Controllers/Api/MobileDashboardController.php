<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Services\Dashboard\OrganizationDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        OrganizationDashboardService $dashboard,
    ): JsonResponse {
        $routers = $organization->networkDevices()->with('location')->orderBy('name')->get();
        $requestedRouter = $request->string('router')->trim()->value();
        $selectedRouter = $requestedRouter === '' ? null : $routers->firstWhere('uuid', $requestedRouter);

        if ($requestedRouter !== '' && ! $selectedRouter) {
            throw ValidationException::withMessages([
                'router' => 'The selected router does not belong to this organization.',
            ]);
        }

        $routerId = $selectedRouter?->id;
        $revenue = $dashboard->revenueTrend($organization, $routerId);
        $plans = $dashboard->topPlans($organization, $routerId);

        return response()->json([
            'data' => [
                'organization' => [
                    'id' => $organization->uuid,
                    'name' => $organization->name,
                    'currency' => $organization->currency,
                    'timezone' => $organization->timezone,
                    'status' => $organization->status->value,
                    'mode' => $organization->mode->value,
                ],
                'scope' => [
                    'router' => $selectedRouter ? $this->router($selectedRouter) : null,
                    'routers' => $routers->map(fn (NetworkDevice $router) => $this->router($router))->values(),
                ],
                'alerts' => [
                    'billing_suspended' => $organization->billing_suspended_at !== null,
                    'payment_profile_required' => $organization->sellsAccess() && ! $organization->paymentProfileActivated(),
                ],
                'pulse' => $dashboard->pulse($organization, $routerId),
                'revenue' => [
                    'labels' => $revenue['labels'],
                    'values_kobo' => array_map(fn (float $value) => (int) round($value * 100), $revenue['values']),
                    'total_kobo' => (int) round($revenue['total'] * 100),
                    'best_kobo' => (int) round($revenue['best'] * 100),
                    'days' => OrganizationDashboardService::TREND_DAYS,
                ],
                'fleet' => $dashboard->fleet($organization, $routerId),
                'sessions_today' => $dashboard->hourly($organization, $routerId),
                'top_plans' => [
                    'items' => collect($plans['labels'])->map(fn (string $name, int $index) => [
                        'name' => $name,
                        'revenue_kobo' => (int) round($plans['values'][$index] * 100),
                    ])->values(),
                    'days' => $plans['days'],
                ],
                'network_health' => $dashboard->recentDevices($organization, $routerId)->map(fn (NetworkDevice $device) => [
                    'id' => $device->uuid,
                    'name' => $device->name,
                    'location' => $device->location->name,
                    'vendor' => $device->vendor->label(),
                    'status' => $device->status->value,
                    'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
                ])->values(),
                'recent_transactions' => $dashboard->recentTransactions($organization, $routerId)->map(fn ($transaction) => [
                    'id' => $transaction->uuid,
                    'reference' => $transaction->reference,
                    'router_name' => $transaction->networkDevice?->name,
                    'channel' => $transaction->channel,
                    'status' => $transaction->status->value,
                    'gross_amount_kobo' => (int) $transaction->gross_amount_kobo,
                    'paid_at' => $transaction->paid_at?->toIso8601String(),
                    'created_at' => $transaction->created_at->toIso8601String(),
                ])->values(),
                'generated_at' => now()->toIso8601String(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    /** @return array{id: string, name: string, location: ?string, status: string} */
    private function router(NetworkDevice $router): array
    {
        return [
            'id' => $router->uuid,
            'name' => $router->name,
            'location' => $router->location?->name,
            'status' => $router->status->value,
        ];
    }
}
