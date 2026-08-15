<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasPublicUuid, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'timezone', 'is_platform_admin'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_platform_admin' => 'boolean'];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withPivot('role', 'joined_at')->withTimestamps();
    }

    public function roleFor(Organization $organization): ?string
    {
        return $this->organizations()->whereKey($organization->id)->first()?->pivot?->role;
    }
}