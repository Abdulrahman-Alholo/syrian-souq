<?php
namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Modules\Vendor\Models\Vendor;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $guarded = ['id'];

    protected $casts = [
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'has_variants' => 'boolean',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    // 👈 العلاقة الجديدة: الخصائص التي يدعمها هذا المنتج (مثل اللون والمقاس)
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & Spatie
    |--------------------------------------------------------------------------
    */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopePriceBetween(Builder $query, $min, $max): Builder
    {
        return $query->whereBetween('min_price', [$min, $max]);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_thumbnail')->singleFile();
        $this->addMediaCollection('product_gallery');
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
}