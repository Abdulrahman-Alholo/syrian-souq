<?php
namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ProductVariant extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'stock' => 'integer',
        'reserved_stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // 👈 العلاقة الجديدة: القيم المرتبطة بهذا المتغير (مثلاً: أحمر ، مقاس 40)
    public function attributeValues()
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'variant_attribute_values',
            'product_variant_id',
            'attribute_value_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & Accessors
    |--------------------------------------------------------------------------
    */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public function getFinalPriceAttribute()
    {
        if ($this->discount_price && $this->discount_price < $this->price) {
            return $this->discount_price;
        }
        return $this->price;
    }

    public function getIsDiscountedAttribute()
    {
        return $this->discount_price && $this->discount_price < $this->price;
    }

    // 🟢 دالة سحرية تحسب المخزون المتاح (الفعلي - المحجوز)
    public function getAvailableStockAttribute()
    {
        return $this->stock - $this->reserved_stock;
    }

    
}