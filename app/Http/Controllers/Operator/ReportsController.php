<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request, Organization $organization): View
    {
        [$from, $to] = $this->range($request);

        return view('operator.reports', [
            'from' => $from,
            'to' => $to,
            'dailySales' => $organization->transactions()
                ->selectRaw('DATE(created_at) as day, SUM(gross_amount_kobo) as total')
                ->where('status', 'successful')
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('day')
                ->get(),
            'usage' => $organization->sessions()
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                ->selectRaw('COUNT(*) as sessions, COALESCE(SUM(input_bytes + output_bytes), 0) as bytes')
                ->first(),
            'topPlans' => $organization->transactions()
                ->join('access_plans', 'transactions.access_plan_id', '=', 'access_plans.id')
                ->where('transactions.status', 'successful')
                ->whereBetween('transactions.created_at', [$from->startOfDay(), $to->endOfDay()])
                ->groupBy('access_plans.name')
                ->selectRaw('access_plans.name, COUNT(*) as sales, SUM(transactions.gross_amount_kobo) as total')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ]);
    }

    public function export(Request $request, Organization $organization): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $transactions = $organization->transactions()
            ->with('accessPlan')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('created_at')
            ->cursor();

        return response()->streamDownload(function () use ($transactions) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Reference', 'Channel', 'Status', 'Plan', 'Amount NGN', 'Paid at']);
            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction->reference,
                    $transaction->channel,
                    $transaction->status->value,
                    $transaction->accessPlan?->name,
                    number_format($transaction->gross_amount_kobo / 100, 2, '.', ''),
                    $transaction->paid_at?->toIso8601String(),
                ]);
            }
            fclose($output);
        }, 'hotfii-sales-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
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