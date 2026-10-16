<?php
namespace App\Filament\Vendor\Resources\ProductResource\Pages;

use App\Filament\Vendor\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    // ==========================================
    // 1. التدخل قبل الحفظ (تجهيز البيانات)
    // ==========================================
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 🛡️ الدرع الواقي
        if (!auth()->user()->vendor) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('خطأ في الحساب')
                ->body('حسابك لا يمتلك ملف متجر مرتبط به.')
                ->send();
            
            $this->halt(); 
        }

        $data['vendor_id'] = auth()->user()->vendor->id;

        // 💰 حساب السعر تلقائياً (الحد الأدنى والأعلى)
        if (!empty($data['variants'])) {
            $prices = array_column($data['variants'], 'price');
            $data['min_price'] = min($prices);
            $data['max_price'] = max($prices);
        } else {
            // في حال كان المنتج بدون متغيرات، السعر الأساسي هو الحدين
            $data['min_price'] = $data['base_price'] ?? 0;
            $data['max_price'] = $data['base_price'] ?? 0;
        }

        return $data;
    }

    // ==========================================
    // 2. التحكم المخصص بعملية الحفظ (إنشاء المنتج + نسخه + خصائصه)
    // ==========================================
    protected function handleRecordCreation(array $data): Model
    {
        // 1. استخراج المتغيرات
        $variants = $data['variants'] ?? [];
        unset($data['variants']);

        // 2. إنشاء المنتج الأساسي
        $product = static::getModel()::create($data);

        // 3. إنشاء النسخ وربط الخصائص
        if (!empty($variants)) {
            foreach ($variants as $variantData) {
                // استخراج الخصائص (Attribute Values) قبل الحفظ
                $attributeValues = $variantData['attributeValues'] ?? [];
                unset($variantData['attributeValues']);

                // إنشاء النسخة في جدول product_variants
                $variant = $product->variants()->create($variantData);

                // 🔥 الخطوة المفقودة: ربط النسخة بالخصائص في جدول variant_attribute_values
                if (!empty($attributeValues)) {
                    $variant->attributeValues()->sync($attributeValues);
                }
            }
        } elseif (!$data['has_variants']) {
             // 4. سيناريو (المنتج البسيط): إنشاء نسخة واحدة افتراضية تحمل السعر والمخزون
             $product->variants()->create([
                 'price' => $data['base_price'] ?? 0,
                 'stock' => 0, // أو إضافة حقل مخزون أساسي لاحقاً
             ]);
        }

        // 🟢 تحديث السعر النهائي للمنتج بناءً على المتغيرات المحفوظة
        if ($product->has_variants && $product->variants()->count() > 0) {
            $product->update([
                'min_price' => $product->variants()->min('price') ?? 0,
                'max_price' => $product->variants()->max('price') ?? 0,
            ]);
        }

        return $product;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}