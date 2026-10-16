<?php
namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Category extends Model
{
    protected $guarded = ['id'];

    // تحويل أنواع البيانات تلقائياً (Casting)
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ==========================================
    // العلاقات (Relationships)
    // ==========================================

    // القسم الأب (الرئيسي)
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // الأقسام الفرعية (الأبناء)
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // المنتجات التابعة لهذا القسم
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // ==========================================
    // نطاقات البحث (Query Scopes) لتنظيف الكنترولر
    // ==========================================
    
    // جلب الأقسام الفعالة فقط
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // جلب الأقسام الرئيسية فقط (التي ليس لها أب)
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function childrenRecursive()
    {   
        return $this->children()->with('childrenRecursive');
    }
}