<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PaymentWebhook;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Throwable;

/**
 * Runtime health for the whole deployment.
 *
 * Read-only: failed jobs are listed but not retried, and webhooks are counted
 * but not replayed. Both of those re-run money-handling code, so they belong on
 * the server where the operator can see the output, not behind a web button.
 */
class SystemController extends Controller
{
    private const FAILED_JOB_SAMPLE = 8;

    /** Statuses that mean somebody has to do something. */
    private const ATTENTION_STATUSES = [
        OrganizationStatus::PaymentReview,
        OrganizationStatus::PaymentRejected,
        OrganizationStatus::Grace,
        OrganizationStatus::Suspended,
    ];

    public function __invoke(): View
    {
        try {
            Redis::connection()->ping();
            $redis = 'online';
        } catch (Throwable) {
            $redis = 'offline';
        }

        $attention = [];

        foreach (self::ATTENTION_STATUSES as $status) {
            $attention[] = [
                'value' => $status->value,
                'label' => str_replace('_', ' ', ucfirst($status->value)),
                'count' => Organization::where('status', $status)->count(),
            ];
        }

        return view('platform.system', [
            'health' => [
                'redis' => $redis,
                'reverb' => config('broadcasting.default') === 'reverb' ? 'configured' : 'disabled',
                'queued_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'pending_webhooks' => PaymentWebhook::whereNull('processed_at')->count(),
            ],
            'queues' => DB::table('jobs')->selectRaw('queue, COUNT(*) as total')->groupBy('queue')->pluck('total', 'queue'),
            'failures' => $this->failures(),
            'runtime' => [
                'Environment' => app()->environment(),
                'Debug mode' => config('app.debug') ? 'on' : 'off',
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Database' => DB::connection()->getDriverName(),
                'Queue driver' => config('queue.default'),
                'Cache store' => config('cache.default'),
                'Session driver' => config('session.driver'),
                'Broadcast driver' => config('broadcasting.default'),
                'Paystack mode' => str_starts_with((string) config('services.paystack.secret'), 'sk_live') ? 'live' : 'test',
            ],
            'attention' => $attention,
            // Freshest row from each background path. A date that has stopped
            // advancing is the cheapest evidence that a worker or the scheduler
            // has died.
            'heartbeats' => [
                'Last successful payment' => Transaction::where('status', 'successful')->max('paid_at'),
                'Last invoice generated' => Invoice::max('created_at'),
                'Last webhook received' => PaymentWebhook::max('created_at'),
                'Last audited action' => AuditLog::max('created_at'),
            ],
        ]);
    }

    /**
     * The newest failures, named and dated.
     *
     * The payload is JSON written by the queue, so the job's own display name is
     * read out of it defensively — a malformed payload should cost the row's
     * label, not the page.
     *
     * @return list<array{queue: string, job: string, failed_at: ?string, exception: string}>
     */
    private function failures(): array
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(self::FAILED_JOB_SAMPLE)
            ->get()
            ->map(function ($row) {
                $payload = json_decode((string) $row->payload, true);

                return [
                    'queue' => (string) $row->queue,
                    'job' => is_array($payload) ? (string) ($payload['displayName'] ?? 'Unknown job') : 'Unknown job',
                    'failed_at' => $row->failed_at ? (string) $row->failed_at : null,
                    // First line only: a stack trace in a table cell is unreadable.
                    'exception' => trim(strtok((string) $row->exception, "\n") ?: ''),
                ];
            })
            ->all();
    }
}
