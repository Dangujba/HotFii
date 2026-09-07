<?php

namespace App\Models;

use App\Domain\Enums\PlanValidityMode;
use App\Models\Concerns\HasPublicUuid;
use App\Support\Bytes;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccessPlan extends Model
{
    use HasPublicUuid;
    protected $fillable = ['organization_id','name','access_type','price_kobo','duration_minutes','data_limit_bytes','download_kbps','upload_kbps','simultaneous_use','validity_days','validity_mode','starts_on_first_use','is_active'];
    protected $attributes = ['validity_mode' => 'midnight'];
    protected function casts(): array { return ['starts_on_first_use'=>'boolean','is_active'=>'boolean','validity_mode'=>PlanValidityMode::class]; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function expiresAt(DateTimeInterface $activatedAt, string $timezone): ?CarbonImmutable
    {
        if (! $this->validity_days) {
            return null;
        }

        $start = CarbonImmutable::instance($activatedAt);
        $expiry = $this->validity_mode === PlanValidityMode::Midnight
            ? $start->setTimezone($timezone)->startOfDay()->addDays((int) $this->validity_days)
            : $start->utc()->addHours((int) $this->validity_days * 24);

        return $expiry->setTimezone(config('app.timezone'));
    }

    public function validityLabel(): string
    {
        if (! $this->validity_days) {
            return 'No expiry';
        }

        return $this->validity_mode === PlanValidityMode::Midnight
            ? $this->validity_days.' calendar '.Str::plural('day', $this->validity_days).', expires at midnight'
            : ($this->validity_days * 24).' hours from activation';
    }

    /**
     * Data cap in the unit an operator entered it in. A 500 MB plan reading
     * "0.49 GB" is no use to anyone, so anything under a gigabyte stays in
     * megabytes and larger caps carry the megabyte figure alongside.
     */
    public function dataAllowance(): ?string
    {
        return $this->data_limit_bytes ? Bytes::detailed($this->data_limit_bytes) : null;
    }
}
