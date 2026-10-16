<?php
namespace App\Filament\Vendor\Resources\ProductResource\Pages;

use App\Filament\Vendor\Resources\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!$data['has_variants']) {
            $data['min_price'] = $data['base_price'] ?? 0;
            $data['max_price'] = $data['base_price'] ?? 0;
        }
        return $data;
    }

    // 👈 هذا الكود يقرأ أسعار النسخ ويحدث الجدول الرئيسي لكي يختفي الصفر
    protected function afterSave(): void
    {
        $product = $this->record;
        
        // 🟢 إعادة حساب السعر في حال عدل التاجر أسعار النسخ
        if ($product->has_variants && $product->variants()->count() > 0) {
            $product->update([
                'min_price' => $product->variants()->min('price') ?? 0,
                'max_price' => $product->variants()->max('price') ?? 0,
            ]);
        } elseif (!$product->has_variants) {
            // إذا ألغى المتغيرات، نعتمد على سعر النسخة الأساسية (الأولى)
            $baseVariant = $product->variants()->first();
            if($baseVariant) {
               $product->update([
                   'min_price' => $baseVariant->price,
                   'max_price' => $baseVariant->price,
               ]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}