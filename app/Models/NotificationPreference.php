<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $attributes = [
        'push_enabled' => true,
        'router_alerts' => true,
        'payment_alerts' => true,
        'invoice_alerts' => true,
        'account_alerts' => true,
    ];

    protected $fillable = [
        'user_id', 'organization_id', 'push_enabled', 'router_alerts',
        'payment_alerts', 'invoice_alerts', 'account_alerts',
    ];

    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'router_alerts' => 'boolean',
            'payment_alerts' => 'boolean',
            'invoice_alerts' => 'boolean',
            'account_alerts' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function allows(string $category): bool
    {
        if (! $this->push_enabled) {
            return false;
        }

        return match ($category) {
            'router' => $this->router_alerts,
            'payment' => $this->payment_alerts,
            'invoice' => $this->invoice_alerts,
            default => $this->account_alerts,
        };
    }
}
