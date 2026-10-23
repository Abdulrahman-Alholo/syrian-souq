<?php

namespace App\Modules\User\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Vendor\Models\Vendor;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use SoftDeletes, Notifiable;

    protected $guarded = ['id'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'is_active'         => 'boolean',
        'last_login_at'     => 'datetime',
    ];

    public function vendor()
    {
        return $this->hasOne(Vendor::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(\App\Modules\Order\Models\Order::class);
    }

    public function isAdmin(): bool
    {
        return $this->account_type === 'admin';
    }

    public function isVendor(): bool
    {
        return $this->account_type === 'vendor';
    }

    /**
     * 🛡️ الجدار الناري الفولاذي للوحات التحكم
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($panel->getId() === 'admin') {
            return $this->isAdmin();
        }

        if ($panel->getId() === 'vendor') {
            return $this->isVendor();
        }

        return false;
    }
}