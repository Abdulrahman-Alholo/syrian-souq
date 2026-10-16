<?php
namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CatalogQueryService
{
    /**
     * جلب المنتجات مع تطبيق الفلاتر والترتيب والترقيم
     *
     * @param array $filters (category_id, vendor_id, search, min_price, max_price, in_stock)
     * @param string $sortBy (newest, price_asc, price_desc)
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getProducts(array $filters = [], string $sortBy = 'newest', int $perPage = 15): LengthAwarePaginator
    {
        // 1. نبدأ بالاستعلام الأساسي للمنتجات الفعالة فقط
        // ملاحظة ذهبية: نستخدم with() لتجنب مشكلة N+1 القاتلة للأداء في الـ API
        $query = Product::query()
            ->active()
            ->with(['variants' => function ($q) {
                $q->active(); // نجلب فقط النسخ الفعالة
            }, 'category', 'vendor']);

        // 2. تطبيق الفلاتر بذكاء (إذا تم إرسالها من الفرونت اند)
        $query = $this->applyFilters($query, $filters);

        // 3. تطبيق الترتيب (Sorting)
        $query = $this->applySorting($query, $sortBy);

        // 4. إرجاع النتيجة مع الترقيم (Pagination)
        return $query->paginate($perPage);
    }

    /**
     * دالة مساعدة لتطبيق الفلاتر بشكل نظيف
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        // الفلترة بالقسم
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // الفلترة بالتاجر (إذا كنا نعرض صفحة متجر لتاجر معين)
        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        // البحث النصي في اسم المنتج (عربي أو إنجليزي)
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('name_en', 'LIKE', "%{$searchTerm}%");
            });
        }

        // الفلترة بنطاق السعر (نستخدم الـ Scope الذي بنيناه مسبقاً)
        if (isset($filters['min_price']) && isset($filters['max_price'])) {
            $query->priceBetween($filters['min_price'], $filters['max_price']);
        }

        // الفلترة: عرض المنتجات المتوفرة في المخزون فقط
        if (!empty($filters['in_stock']) && $filters['in_stock'] === true) {
            $query->whereHas('variants', function ($q) {
                $q->inStock(); // نستخدم الـ Scope الخاص بالـ Variant
            });
        }

        return $query;
    }

    /**
     * دالة مساعدة لتطبيق الترتيب
     */
    private function applySorting(Builder $query, string $sortBy): Builder
    {
        return match ($sortBy) {
            'price_asc'  => $query->orderBy('min_price', 'asc'),
            'price_desc' => $query->orderBy('max_price', 'desc'),
            'newest'     => $query->latest(),
            default      => $query->latest(),
        };
    }
}