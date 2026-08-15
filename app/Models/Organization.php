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

    public function canCollectLivePayments(): bool
    {
        return $this->sellsAccess()
            && in_array($this->status, [OrganizationStatus::Live, OrganizationStatus::Trial], true)
            && $this->live_payments_enabled_at !== null
            && filled($this->paystack_subaccount_code);
    }
}