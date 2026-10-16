<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Vendor\Models\Vendor;

class PayoutRequest extends Model
{
    // السماح بإدخال البيانات لجميع الحقول باستثناء الـ id
    protected $guarded = ['id'];

    // تحويل نوع البيانات تلقائياً
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * علاقة طلب السحب بالتاجر (كل طلب يعود لتاجر واحد)
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}