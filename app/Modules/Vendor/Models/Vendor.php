<?php
namespace App\Modules\Vendor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\User\Models\User;
use App\Modules\Catalog\Models\Product;

class Vendor extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
        'suspended_at' => 'datetime',
        'commission_rate' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // علاقة التاجر بطلبات السحب
    public function payoutRequests()
    {
        return $this->hasMany(\App\Modules\Finance\Models\PayoutRequest::class);
    }

    // علاقة التاجر بالسجل المالي (كشف الحساب)
    public function transactions()
    {
        return $this->hasMany(\App\Modules\Finance\Models\VendorTransaction::class);
    }

    // داخل مودل Vendor.php
public function getAvailableBalanceAttribute()
{
    $credits = $this->transactions()
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('amount');

    $debits = $this->transactions()
                   ->where('type', 'debit')
                   ->where('status', 'completed')
                   ->sum('amount');

    return $credits - $debits;
}
}