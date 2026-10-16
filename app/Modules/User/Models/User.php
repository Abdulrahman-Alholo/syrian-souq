<?php
namespace App\Modules\User\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Vendor\Models\Vendor;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use SoftDeletes;
use Notifiable;
    protected $guarded = ['id'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // علاقة المستخدم بالمتجر (إذا كان تاجراً)
    public function vendor()
    {
        return $this->hasOne(Vendor::class);
    }

    // علاقة المستخدم بعناوين الشحن
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

   

    // علاقة المستخدم بطلباته
    public function orders()
    {
        return $this->hasMany(\App\Modules\Order\Models\Order::class);
    }

    // مساعد لمعرفة نوع الحساب بسرعة
    public function isAdmin(): bool { return $this->account_type === 'admin'; }
    public function isVendor(): bool { return $this->account_type === 'vendor'; }

    /**
     * الجدار الناري للوحات التحكم
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // 1. لوحة الإدارة (مسموحة فقط للـ Admin)
        if ($panel->getId() === 'admin') {
            return $this->isAdmin() && $this->is_active;
        }

        // 2. لوحة التجار (مسموحة للتاجر الفعال)
        if ($panel->getId() === 'vendor') {
            return $this->isVendor() && $this->is_active;
        }

        return false;
    }
}