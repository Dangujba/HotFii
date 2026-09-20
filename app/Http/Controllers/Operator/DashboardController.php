<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Dashboard\OrganizationDashboardService;
use App\Support\OrganizationRouterFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        OrganizationDashboardService $dashboard,
    ): View {
        [$routers, $routerId] = OrganizationRouterFilter::resolve($request, $organization);

        return view('dashboard.index', [
            'revenue' => $dashboard->revenueTrend($organization, $routerId),
            'fleet' => $dashboard->fleet($organization, $routerId),
            'hourly' => $dashboard->hourly($organization, $routerId),
            'plans' => $dashboard->topPlans($organization, $routerId),
            'devices' => $dashboard->recentDevices($organization, $routerId),
            'transactions' => $dashboard->recentTransactions($organization, $routerId),
            'routers' => $routers,
            'selectedRouter' => $routers->firstWhere('id', $routerId),
            'selectedRouterId' => $routerId,
        ]);
    }
}
