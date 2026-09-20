<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Reports\OrganizationReportService;
use App\Support\OrganizationRouterFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(private readonly OrganizationReportService $reports) {}

    /**
     * The ledger itself belongs in the CSV. A period with tens of thousands of
     * transactions would otherwise render a PDF nobody can open, so the listing
     * is capped and the PDF says so on the page.
     */
    private const PDF_ROW_LIMIT = 250;

    public function index(
        Request $request,
        Organization $organization,
    ): View {
        [$from, $to] = $this->range($request);
        [$routers, $routerId] = OrganizationRouterFilter::resolve($request, $organization);
        $selectedRouter = $routers->firstWhere('id', $routerId);
        $report = $this->reports->build($organization, $from, $to, $selectedRouter);
        $salesSeries = collect($report['sales_trend']['series'])
            ->map(fn (array $values) => collect($values)->map(fn (int $value) => round($value / 100, 2))->all())
            ->all();
        $channels = $report['channels']->map(fn (array $channel) => [
            ...$channel,
            'value' => round($channel['total_kobo'] / 100, 2),
        ]);

        return view('operator.reports', [
            'from' => $from,
            'to' => $to,
            'summary' => (object) $report['summary'],
            'usage' => (object) $report['usage'],
            'salesTrend' => ['labels' => $report['sales_trend']['labels'], 'series' => $salesSeries],
            'channels' => $channels,
            'topPlans' => $report['top_plans'],
            'routers' => $routers,
            'selectedRouter' => $selectedRouter,
            'usageTrend' => [
                'labels' => $report['usage_trend']['labels'],
                'sessions' => $report['usage_trend']['sessions'],
                'megabytes' => collect($report['usage_trend']['bytes'])
                    ->map(fn (int $value) => round($value / 1_048_576, 2))
                    ->all(),
            ],
        ]);
    }

    public function export(Request $request, Organization $organization): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        [, $routerId] = OrganizationRouterFilter::resolve($request, $organization);
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $transactions = $organization->transactions()
            ->with('accessPlan', 'networkDevice')
            ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router))
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
        [$from, $to] = $this->range($request);
        [$routers, $routerId] = OrganizationRouterFilter::resolve($request, $organization);
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $paidAt = 'COALESCE(paid_at, transactions.created_at)';
        $channel = "CASE
            WHEN reference LIKE 'HF-VCH-%' THEN 'voucher'
            WHEN channel = 'cash' THEN 'cash'
            ELSE 'online'
        END";
        // Qualified, because the top-plans query joins access_plans and that
        // table carries a created_at and a name of its own.
        $transactions = fn () => $organization->transactions()
            ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router))
            ->whereBetween(DB::raw($paidAt), $window);

        $rows = $transactions()->with('accessPlan', 'networkDevice')->orderByRaw($paidAt)->limit(self::PDF_ROW_LIMIT)->get();

        $pdf = Pdf::loadView('operator.report-pdf', [
            'organization' => $organization,
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
            'selectedRouter' => $routers->firstWhere('id', $routerId),
            'summary' => $transactions()->selectRaw(
                "COUNT(*) as attempts,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN 1 ELSE 0 END), 0) as sales,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN gross_amount_kobo ELSE 0 END), 0) as gross_kobo,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN gateway_fee_kobo ELSE 0 END), 0) as gateway_kobo,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN platform_fee_kobo ELSE 0 END), 0) as platform_kobo"
            )->first(),
            'usage' => $organization->sessions()
                ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router))
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

        return $pdf->setPaper('a4')
            ->download('hotfii-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf');
    }

    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            isset($data['from']) ? Carbon::parse($data['from']) : now()->subDays(29),
            isset($data['to']) ? Carbon::parse($data['to']) : now(),
        ];
    }
}
