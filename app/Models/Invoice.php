<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'number', 'billing_period', 'subtotal_kobo',
        'total_kobo', 'status', 'due_at',
    ];

    protected function casts(): array
    {
        return ['billing_period' => 'date', 'due_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_at !== null && $this->due_at->isPast();
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'open')->where('due_at', '<=', now());
    }

    /**
     * Record that this invoice has been settled.
     *
     * paid_at and the settlement columns are deliberately not fillable: an
     * invoice going from open to paid is the moment a suspension becomes
     * reversible, and it must happen in one place that always records how the
     * money arrived. Callers go through InvoiceSettlement rather than here, so
     * the organization's status is reconsidered in the same breath.
     *
     * Returns false when the invoice was already paid, so a Paystack callback
     * arriving alongside its webhook settles it once and audits it once.
     */
    public function markPaid(string $method, ?string $reference = null, ?\DateTimeInterface $at = null): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        return $this->forceFill([
            'status' => 'paid',
            'payment_method' => $method,
            'payment_reference' => $reference,
            'paid_at' => $at ?? now(),
        ])->save();
    }
}
