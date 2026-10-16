<?php

namespace App\Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Catalog\Models\ProductVariant;

class OrderItem extends Model
{
    protected $guarded = [];

    // 🧠 دالة لحساب الإجمالي فورياً بدون تخزينه في قاعدة البيانات
    public function getTotalAttribute()
    {
        return $this->quantity * $this->unit_price;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }// جلب النسخة (الـ Variant) المرتبطة بهذا العنصر
    public function productVariant()
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\ProductVariant::class, 'product_variant_id');
    }

    // جلب الطلب الفرعي الخاص بالتاجر (عشان نحتاجه لاحقاً بلوحة التاجر)
    public function vendorOrder()
    {
        return $this->belongsTo(VendorOrder::class, 'vendor_order_id');
    }
}