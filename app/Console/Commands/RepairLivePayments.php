<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\PaymentProfile;
use Illuminate\Console\Command;

/**
 * Repairs organizations that were approved for live payments but never had the
 * timestamp written.
 *
 * Before the fix in Organization::activateLivePayments(), approval passed
 * live_payments_enabled_at to update(). The column is not fillable, so
 * Model::preventSilentlyDiscardingAttributes threw in dev and *silently
 * discarded* it in production — leaving the organization Live, holding a valid
 * Paystack subaccount code, and refused by canCollectLivePayments() on every
 * sale. The owner saw "Activate payments" forever; the portal returned 403.
 *
 * The write path can no longer produce that state. This repairs rows already
 * written, and stays in the tree because it is the diagnostic for the symptom:
 * run it with --dry-run and it answers "is this that bug, and on which
 * organizations", without changing anything.
 */
class RepairLivePayments extends Command
{
    protected $signature = 'hotfii:repair-live-payments
        {--dry-run : List what would change and exit without writing}';

    protected $description = 'Restore the activation timestamp on organizations approved for live payments without one';

    public function handle(): int
    {
        // Deliberately narrow: an approved profile AND a subaccount code is the
        // evidence that approval really ran and only the timestamp was lost.
        // Anything else is a different fault and must not be papered over here.
        $affected = Organization::query()
            ->whereNull('live_payments_enabled_at')
            ->whereNotNull('paystack_subaccount_code')
            ->whereHas('paymentProfile', fn ($query) => $query->where('status', 'approved'))
            ->with('paymentProfile')
            ->get();

        if ($affected->isEmpty()) {
            $this->info('Nothing to repair: every approved organization has its activation timestamp.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d organization(s) approved for live payments with no activation timestamp:', $affected->count()));

        $rows = $affected->map(function (Organization $organization): array {
            return [
                $organization->name,
                $organization->status->value,
                $this->activatedAt($organization->paymentProfile)->format('j M Y H:i'),
            ];
        });

        $this->table(['Organization', 'Status', 'Timestamp to restore'], $rows);

        if ($this->option('dry-run')) {
            $this->line('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Restore the activation timestamp on these organizations?', true)) {
            return self::FAILURE;
        }

        foreach ($affected as $organization) {
            $organization->activateLivePayments(
                (string) $organization->paystack_subaccount_code,
                $this->activatedAt($organization->paymentProfile),
            );
        }

        $this->info(sprintf('Repaired %d organization(s). They can collect live payments again.', $affected->count()));

        return self::SUCCESS;
    }

    /**
     * When approval actually happened. Backdated rather than stamped now(), so
     * trial windows and monthly invoicing keep counting from the real date.
     */
    private function activatedAt(?PaymentProfile $profile): \DateTimeInterface
    {
        return $profile?->reviewed_at ?? $profile?->auto_approved_at ?? now();
    }
}
