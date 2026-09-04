<?php

namespace App\Models;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'mode', 'status', 'billing_plan', 'currency', 'timezone',
        'paystack_subaccount_code', 'branding', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'mode' => OrganizationMode::class,
            'status' => OrganizationStatus::class,
            'billing_plan' => BillingPlan::class,
            'live_payments_enabled_at' => 'datetime',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'branding' => 'array',
            'settings' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'joined_at')->withTimestamps();
    }

    public function locations(): HasMany { return $this->hasMany(Location::class); }
    public function networkDevices(): HasMany { return $this->hasMany(NetworkDevice::class); }
    public function accessPlans(): HasMany { return $this->hasMany(AccessPlan::class); }
    public function accessGroups(): HasMany { return $this->hasMany(AccessGroup::class); }
    public function customers(): HasMany { return $this->hasMany(Customer::class); }
    public function vouchers(): HasMany { return $this->hasMany(Voucher::class); }
    public function voucherBatches(): HasMany { return $this->hasMany(VoucherBatch::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
    public function sessions(): HasMany { return $this->hasMany(HotspotSession::class); }
    public function credentials(): HasMany { return $this->hasMany(AccessCredential::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function paymentProfile(): HasOne { return $this->hasOne(PaymentProfile::class); }

    public function sellsAccess(): bool
    {
        return $this->mode->sellsAccess();
    }

    public function inTrial(): bool
    {
        return $this->status === OrganizationStatus::Trial && $this->trial_ends_at?->isFuture();
    }

    /**
     * Collecting real money is gated on the payment profile alone, not on the
     * account lifecycle. An organization is Live from registration so it can
     * build plans, provision routers and sell in sandbox; the settlement
     * subaccount is what unlocks live collection.
     */
    public function canCollectLivePayments(): bool
    {
        return $this->sellsAccess()
            && ! in_array($this->status, [OrganizationStatus::Suspended, OrganizationStatus::PaymentRejected], true)
            && $this->live_payments_enabled_at !== null
            && filled($this->paystack_subaccount_code);
    }

    /**
     * True once a payment profile exists and is approved. Distinct from
     * canCollectLivePayments(), which also refuses suspended accounts.
     */
    public function paymentProfileActivated(): bool
    {
        return $this->live_payments_enabled_at !== null
            && filled($this->paystack_subaccount_code);
    }

    /**
     * Unlock live collection: the subaccount money settles into, and the
     * timestamp both gates above read.
     *
     * Every caller goes through here rather than writing the columns itself.
     * live_payments_enabled_at is deliberately not fillable, so passing it to
     * update() throws in dev and is *silently discarded* in production — which
     * is how organizations ended up Live, holding a subaccount code, and unable
     * to take a single payment. Keeping the write in one place makes that
     * mistake unavailable at the call site.
     *
     * $at backdates the activation when repairing a profile approved earlier,
     * so trial and billing windows stay honest.
     */
    public function activateLivePayments(string $subaccountCode, ?\DateTimeInterface $at = null): bool
    {
        return $this->forceFill([
            'status' => OrganizationStatus::Live,
            'paystack_subaccount_code' => $subaccountCode,
            'live_payments_enabled_at' => $at ?? now(),
        ])->save();
    }

    /**
     * Withdraw live collection. The subaccount code stays on file: it is what
     * the owner corrects and resubmits against, and clearing it would orphan
     * the subaccount already open at Paystack.
     */
    public function revokeLivePayments(OrganizationStatus $status = OrganizationStatus::PaymentRejected): bool
    {
        return $this->forceFill([
            'status' => $status,
            'live_payments_enabled_at' => null,
        ])->save();
    }
}