<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    protected $fillable = ['provider','event_id','event_type','payload','status','attempts','processed_at','last_error'];
    protected function casts(): array { return ['payload'=>'array','processed_at'=>'datetime']; }
}