<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Services\Reports\OrganizationReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileReportController extends Controller
{
    private const PDF_ROW_LIMIT = 250;

    public function index(Request $request, Organization $organization, OrganizationReportService $reports): JsonResponse
    {
        [$from, $to, $router] = $this->filters($request, $organization);
        $report = $reports->build($organization, $from, $to, $router);

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'router_id' => $router?->uuid,
            ...$report,
            'top_plans' => $report['top_plans']->map(fn ($plan) => [
                'name' => $plan->name,
                'sales' => (int) $plan->sales,
                'total_kobo' => (int) $plan->total,
            ])->values(),
            'options' => [
                'routers' => $organization->networkDevices()->orderBy('name')->get()->map(fn (NetworkDevice $device) => [
                    'id' => $device->uuid,
                    'name' => $device->name,
                ])->values(),
            ],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function exportCsv(Request $request, Organization $organization): StreamedResponse
    {
        [$from, $to, $router] = $this->filters($request, $organization);
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $transactions = $organization->transactions()
            ->with('accessPlan', 'networkDevice')
            ->when($router, fn ($query) => $query->where('network_device_id', $router->id))
            ->whereBetween(DB::raw('COALESCE(paid_at, transactions.created_at)'), $window)
            ->orderByRaw('COALESCE(paid_at, transactions.created_at)')
            ->lazy(500);

        return response()->streamDownload(function () use ($transactions) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Reference', 'Router', 'Channel', 'Status', 'Plan', 'Amount NGN', 'Paid at']);
            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction->reference,
                    $transaction->networkDevice?->name ?? 'Unattributed',
                    str_starts_with($transaction->reference, 'HF-VCH-')
                        ? 'Voucher'
                        : ($transaction->channel === 'cash' ? 'Direct cash' : 'Online'),
                    $transaction->status->value,
                    $transaction->accessPlan?->name,
                    number_format($transaction->gross_amount_kobo / 100, 2, '.', ''),
                    $transaction->paid_at?->toIso8601String(),
                ]);
            }
            fclose($output);
        }, 'hotfii-sales-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Request $request, Organization $organization): Response
    {
        [$from, $to, $router] = $this->filters($request, $organization);
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $paidAt = 'COALESCE(paid_at, transactions.created_at)';
        $channel = "CASE
            WHEN reference LIKE 'HF-VCH-%' THEN 'voucher'
            WHEN channel = 'cash' THEN 'cash'
            ELSE 'online'
        END";
        $transactions = fn () => $organization->transactions()
            ->when($router, fn ($query) => $query->where('network_device_id', $router->id))
            ->whereBetween(DB::raw($paidAt), $window);
        $rows = $transactions()->with('accessPlan', 'networkDevice')->orderByRaw($paidAt)->limit(self::PDF_ROW_LIMIT)->get();
        $pdf = Pdf::loadView('operator.report-pdf', [
            'organization' => $organization,
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
            'selectedRouter' => $router,
            'summary' => $transactions()->selectRaw(
                "COUNT(*) as attempts,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN 1 ELSE 0 END), 0) as sales,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN gross_amount_kobo ELSE 0 END), 0) as gross_kobo,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN gateway_fee_kobo ELSE 0 END), 0) as gateway_kobo,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN platform_fee_kobo ELSE 0 END), 0) as platform_kobo"
            )->first(),
            'usage' => $organization->sessions()
                ->when($router, fn ($query) => $query->where('network_device_id', $router->id))
                ->whereBetween('created_at', $window)
                ->selectRaw('COUNT(*) as sessions, COALESCE(SUM(input_bytes + output_bytes), 0) as bytes')
                ->first(),
            'byChannel' => $transactions()
                ->where('status', 'successful')
                ->groupBy(DB::raw($channel))
                ->selectRaw("$channel as channel, COUNT(*) as sales, SUM(gross_amount_kobo) as total")
                ->orderByDesc('total')
                ->get(),
            'topPlans' => $transactions()
                ->join('access_plans', 'transactions.access_plan_id', '=', 'access_plans.id')
                ->where('transactions.status', 'successful')
                ->groupBy('access_plans.name')
                ->selectRaw('access_plans.name, COUNT(*) as sales, SUM(transactions.gross_amount_kobo) as total')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'daily' => $transactions()
                ->where('transactions.status', 'successful')
                ->selectRaw("DATE($paidAt) as day, COUNT(*) as sales, SUM(gross_amount_kobo) as total")
                ->groupBy(DB::raw("DATE($paidAt)"))
                ->orderBy('day')
                ->get(),
            'rows' => $rows,
            'rowLimit' => self::PDF_ROW_LIMIT,
        ]);

        return $pdf->setPaper('a4')->download('hotfii-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf');
    }

    private function filters(Request $request, Organization $organization): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'router' => ['nullable', 'uuid'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : now()->subDays(29);
        $to = isset($data['to']) ? Carbon::parse($data['to']) : now();
        abort_if($from->diffInDays($to) > 366, 422, 'Choose a report period of 366 days or less.');
        $router = isset($data['router'])
            ? $organization->networkDevices()->where('uuid', $data['router'])->firstOrFail()
            : null;

        return [$from, $to, $router];
    }
}
