<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherBatch extends Model
{
    use HasPublicUuid;
    protected $fillable = ['organization_id','access_plan_id','assigned_user_id','reference','quantity','retail_price_kobo','status','printed_at'];
    protected function casts(): array { return ['printed_at'=>'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function accessPlan(): BelongsTo { return $this->belongsTo(AccessPlan::class); }
    public function vouchers(): HasMany { return $this->hasMany(Voucher::class); }
}