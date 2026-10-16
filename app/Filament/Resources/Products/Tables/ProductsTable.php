<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use App\Modules\Catalog\Models\Product;
use App\Filament\Resources\Products\ProductResource;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('thumbnail')
                    ->collection('product_thumbnail')
                    ->label('الصورة')
                    ->circular(),
                
                TextColumn::make('name_ar')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $record): string => $record->slug), // إظهار السلاج تحت الاسم
                
                // 👑 الأهم للإدارة: معرفة المتجر صاحب المنتج
                TextColumn::make('vendor.store_name') 
                    ->label('المتجر')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                
                TextColumn::make('category.name_ar')
                    ->label('القسم')
                    ->sortable(),
                
                TextColumn::make('price_range')
                    ->label('السعر (SYP)')
                    ->getStateUsing(fn (Product $record) => 
                        floatval($record->min_price) == floatval($record->max_price) 
                            ? number_format($record->min_price, 0) 
                            : number_format($record->min_price, 0) . ' - ' . number_format($record->max_price, 0)
                    ),
                
                // 🛑 زر الرقابة السريع (المدير يقدر يطفي المنتج بكبسة زر من برا)
                ToggleColumn::make('is_active')
                    ->label('مفعل (يعرض بالسوق)'),
            ])
            ->filters([
                // 🔍 فلاتر قوية للإدارة
                SelectFilter::make('vendor_id')
                    ->relationship('vendor', 'store_name')
                    ->label('فلترة حسب المتجر')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('category_id')
                    ->relationship('category', 'name_ar')
                    ->label('فلترة حسب القسم'),
                
                TernaryFilter::make('is_active')
                    ->label('حالة التفعيل')
                    ->trueLabel('المنتجات المفعلة فقط')
                    ->falseLabel('المنتجات الموقوفة'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }
}