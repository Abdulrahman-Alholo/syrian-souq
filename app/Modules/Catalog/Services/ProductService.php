<?
namespace App\Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\DTOs\ProductData;

class ProductService
{
    public function create(ProductData $data): Product
    {
        return DB::transaction(function () use ($data) {
            
            // 1. إنشاء المنتج مع أسعار صفرية مؤقتة لتجاوز قيد قاعدة البيانات (NOT NULL)
            $product = Product::create([
                'vendor_id' => $data->vendor_id,
                'category_id' => $data->category_id,
                'name_ar' => $data->name_ar,
                'name_en' => $data->name_en,
                'slug' => $data->slug,
                'description' => $data->description,
                'has_variants' => $data->has_variants,
                'min_price' => 0, // قيمة مؤقتة
                'max_price' => 0, // قيمة مؤقتة
                'is_active' => true
            ]);

            $prices = [];

            // 2. إنشاء الـ Variants بالقيود الصحيحة
            foreach ($data->variants as $variant) {
                $v = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variant['sku'],
                    // الحماية من الـ null واستخدام القيم الافتراضية للـ unique index
                    'color' => $variant['color'] ?? 'standard', 
                    'size' => $variant['size'] ?? 'universal',
                    'price' => $variant['price'],
                    'discount_price' => $variant['discount_price'] ?? null,
                    'stock' => $variant['stock'] ?? 0,
                    'is_active' => true
                ]);

                // تحديد السعر الفعلي (سعر الخصم إن وجد، وإلا السعر الأساسي)
                $prices[] = $v->discount_price ?? $v->price;
            }

            // 3. تحديث السعر الأدنى والأعلى الحقيقي للمنتج
            $product->update([
                'min_price' => min($prices),
                'max_price' => max($prices)
            ]);

            return $product->load('variants');
        });
    }
}