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
                    $graceEndsAt = $invoice->due_at->copy()->addDays((int) config('hotfii.commerce.grace_days'));
                    $organization->update([
                        'status' => $graceEndsAt->isPast()
                            ? OrganizationStatus::Suspended
                            : OrganizationStatus::Grace,
                    ]);
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