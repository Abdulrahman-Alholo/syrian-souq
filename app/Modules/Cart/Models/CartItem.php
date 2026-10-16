<?php

namespace App\Modules\Cart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Catalog\Models\ProductVariant;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_variant_id',
        'quantity',
    ];

    // 🟢 الضربة القاضية لمشكلة N+1 (Eager Loading تلقائي للنسخة)
    protected $with = ['variant'];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getSubtotalAttribute()
{
    // استخدمنا final_price اللي أنت برمجتها بذكاء في ProductVariant
    return $this->quantity * ($this->variant->final_price ?? 0); 
}
}