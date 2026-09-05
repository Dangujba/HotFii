<?php

namespace App\Jobs;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnforceSubscriptionGrace implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 45;

    public function __construct()
    {
        $this->onQueue('payments');
    }

    public function handle(): void
    {
        Invoice::query()
            ->where('status', 'open')
            ->where('due_at', '<=', now())
            ->with('organization')
            ->chunkById(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $organization = $invoice->organization;

                    if ($organization === null) {
                        continue;
                    }

                    $graceEndsAt = $invoice->due_at->copy()->addDays((int) config('hotfii.commerce.grace_days'));

                    // markBillingSuspended, not update(): it stamps
                    // billing_suspended_at, which is the only thing that later
                    // tells an automatic restriction apart from one a person
                    // applied from the console. Without that mark this job
                    // would either strand paying customers or start reversing
                    // suspensions imposed for abuse.
                    $organization->markBillingSuspended(
                        $graceEndsAt->isPast()
                            ? OrganizationStatus::Suspended
                            : OrganizationStatus::Grace,
                    );
                }
            });

        // Anything held over billing that no longer owes an overdue invoice is
        // released here. This is the counterpart to the pass above, and the
        // reason the console's reactivate button used to be pointless: it lifted
        // a suspension the very next run put straight back, because nothing ever
        // settled the invoice underneath it. Settling now lifts the account
        // within the hour even when no one is watching.
        Organization::query()
            ->whereNotNull('billing_suspended_at')
            ->whereDoesntHave('invoices', fn ($query) => $query->where('status', 'open')->where('due_at', '<=', now()))
            ->chunkById(100, function ($organizations) {
                foreach ($organizations as $organization) {
                    $organization->clearBillingSuspension();
                }
            });

        Organization::query()
            ->where('status', OrganizationStatus::Trial)
            ->where(fn ($query) => $query
                ->where('trial_ends_at', '<=', now())
                ->orWhere('trial_sales_kobo', '>=', config('hotfii.commerce.trial_sales_cap_kobo')))
            ->chunkById(100, function ($organizations) {
                foreach ($organizations as $organization) {
                    $updates = ['status' => OrganizationStatus::Live];
                    if ($organization->mode === OrganizationMode::Commerce) {
                        $updates['billing_plan'] = $organization->trial_sales_kobo <= config('hotfii.commerce.micro_sales_limit_kobo')
                            ? BillingPlan::MicroSeller
                            : BillingPlan::StandardSeller;
                    }
                    $organization->update($updates);
                }
            });
    }
}