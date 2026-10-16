<?php

namespace App\Modules\Finance\Models; // أو المسار المناسب لديك

use Illuminate\Database\Eloquent\Model;
use App\Modules\Vendor\Models\Vendor;
use Illuminate\Support\Str;

class VendorTransaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // التوليد التلقائي للـ UUID عند الإنشاء
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });

        // 🛑 الحماية الصارمة: منع التعديل أو الحذف نهائياً
        static::updating(function () {
            throw new \Exception("Strict Mode: لا يمكن تعديل السجلات المالية بعد إنشائها.");
        });

        static::deleting(function () {
            throw new \Exception("Strict Mode: لا يمكن حذف السجلات المالية.");
        });
    }

    // العلاقة المورفية (لربط الحركة بالطلب، أو بالدفعات Payouts)
    public function reference()
    {
        return $this->morphTo();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}