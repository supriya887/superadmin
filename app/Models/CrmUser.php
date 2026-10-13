<?php

namespace App\Models;

use App\Notifications\CrmResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class CrmUser extends Authenticatable implements CanResetPasswordContract
{
    use HasApiTokens, CanResetPassword, HasFactory, Notifiable;

    /**
     * CRM database connection.
     */
    protected $connection = 'pgsql_crm';

    /**
     * CRM users table.
     */
    protected $table = 'crm_users';

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'role',
        'status',
        'avatar',
        'profile_image',
        'manager',
        'max_quot_discount',
        'jd_lead_assign',
        'last_login_at',
        'ip_address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        $img = $this->avatar ?: $this->profile_image;
        if (!$img) {
            return null;
        }
        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
            return $img;
        }
        return asset('storage/' . ltrim($img, '/'));
    }

    public function tokens(): MorphMany
    {
        return $this->morphMany(CrmPersonalAccessToken::class, 'tokenable');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CrmResetPassword($token));
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeUsers($query)
    {
        return $query->where('role', 'user');
    }
}
