<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    /**
     * The ledger itself belongs in the CSV. A period with tens of thousands of
     * transactions would otherwise render a PDF nobody can open, so the listing
     * is capped and the PDF says so on the page.
     */
    private const PDF_ROW_LIMIT = 250;

    public function index(Request $request, Organization $organization): View
    {
        [$from, $to] = $this->range($request);
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $paidAt = 'COALESCE(paid_at, transactions.created_at)';
        $day = "DATE($paidAt)";
        $channel = "CASE
            WHEN channel = 'cash' AND reference LIKE 'HF-VCH-%' THEN 'voucher'
            WHEN channel = 'cash' THEN 'cash'
            ELSE 'online'
        END";

        $transactions = fn () => $organization->transactions()
            ->where('status', 'successful')
            ->whereBetween(DB::raw($paidAt), $window);

        $summary = $transactions()
            ->selectRaw('COUNT(*) as sales, COALESCE(SUM(gross_amount_kobo), 0) as gross_kobo')
            ->first();

        $dailyRows = $transactions()
            ->selectRaw("$day as day, $channel as sale_channel, SUM(gross_amount_kobo) as total")
            ->groupBy(DB::raw($day), DB::raw($channel))
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString().'|'.$row->sale_channel);

        $usageRows = $organization->sessions()
            ->whereBetween(DB::raw('COALESCE(started_at, created_at)'), $window)
            ->selectRaw('DATE(COALESCE(started_at, created_at)) as day, COUNT(*) as sessions, COALESCE(SUM(input_bytes + output_bytes), 0) as bytes')
            ->groupBy(DB::raw('DATE(COALESCE(started_at, created_at))'))
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $labels = [];
        $salesSeries = ['online' => [], 'voucher' => [], 'cash' => []];
        $sessionValues = [];
        $megabyteValues = [];

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            $dateKey = $date->toDateString();
            $labels[] = $date->format('j M');

            foreach (array_keys($salesSeries) as $channelKey) {
                $salesSeries[$channelKey][] = round(
                    (int) ($dailyRows->get($dateKey.'|'.$channelKey)?->total ?? 0) / 100,
                    2,
                );
            }

            $usageDay = $usageRows->get($dateKey);
            $sessionValues[] = (int) ($usageDay?->sessions ?? 0);
            $megabyteValues[] = round((int) ($usageDay?->bytes ?? 0) / 1_048_576, 2);
        }

        $channelRows = $transactions()
            ->selectRaw("$channel as sale_channel, COUNT(*) as sales, SUM(gross_amount_kobo) as total")
            ->groupBy(DB::raw($channel))
            ->get()
            ->keyBy('sale_channel');

        $channels = collect([
            'online' => 'Online',
            'voucher' => 'Vouchers',
            'cash' => 'Direct cash',
        ])->map(function (string $label, string $key) use ($channelRows): array {
            $row = $channelRows->get($key);

            return [
                'key' => $key,
                'label' => $label,
                'sales' => (int) ($row?->sales ?? 0),
                'value' => round((int) ($row?->total ?? 0) / 100, 2),
            ];
        })->values();

        $topPlans = $transactions()
            ->join('access_plans', 'transactions.access_plan_id', '=', 'access_plans.id')
            ->groupBy('access_plans.name')
            ->selectRaw('access_plans.name, COUNT(*) as sales, SUM(transactions.gross_amount_kobo) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('operator.reports', [
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'usage' => $organization->sessions()
                ->whereBetween(DB::raw('COALESCE(started_at, created_at)'), $window)
                ->selectRaw('COUNT(*) as sessions, COALESCE(SUM(input_bytes + output_bytes), 0) as bytes')
                ->first(),
            'salesTrend' => ['labels' => $labels, 'series' => $salesSeries],
            'channels' => $channels,
            'topPlans' => $topPlans,
            'usageTrend' => [
                'labels' => $labels,
                'sessions' => $sessionValues,
                'megabytes' => $megabyteValues,
            ],
        ]);
    }

    public function export(Request $request, Organization $organization): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $transactions = $organization->transactions()
            ->with('accessPlan')
            ->whereBetween(DB::raw('COALESCE(paid_at, transactions.created_at)'), $window)
            ->orderByRaw('COALESCE(paid_at, transactions.created_at)')
            ->cursor();

        return response()->streamDownload(function () use ($transactions) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Reference', 'Channel', 'Status', 'Plan', 'Amount NGN', 'Paid at']);
            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction->reference,
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
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $paidAt = 'COALESCE(paid_at, transactions.created_at)';
        $channel = "CASE
            WHEN reference LIKE 'HF-VCH-%' THEN 'voucher'
            WHEN channel = 'cash' THEN 'cash'
            ELSE 'online'
        END";
        // Qualified, because the top-plans query joins access_plans and that
        // table carries a created_at and a name of its own.
        $transactions = fn () => $organization->transactions()->whereBetween(DB::raw($paidAt), $window);

        $rows = $transactions()->with('accessPlan')->orderByRaw($paidAt)->limit(self::PDF_ROW_LIMIT)->get();

        $pdf = Pdf::loadView('operator.report-pdf', [
            'organization' => $organization,
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
            'summary' => $transactions()->selectRaw(
                "COUNT(*) as attempts,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN 1 ELSE 0 END), 0) as sales,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN gross_amount_kobo ELSE 0 END), 0) as gross_kobo,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN gateway_fee_kobo ELSE 0 END), 0) as gateway_kobo,
                 COALESCE(SUM(CASE WHEN status = 'successful' THEN platform_fee_kobo ELSE 0 END), 0) as platform_kobo"
            )->first(),
            'usage' => $organization->sessions()
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
