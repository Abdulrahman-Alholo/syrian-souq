<?php

namespace App\Filament\Vendor\Resources;

use App\Filament\Vendor\Resources\TransactionResource\Pages;
use App\Modules\Finance\Models\VendorTransaction;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = VendorTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $modelLabel = 'حركة مالية';
    protected static ?string $pluralModelLabel = 'كشف الحساب';
    protected static ?int $navigationSort = 2; // نضعه قبل طلبات السحب

    // 🛡️ الحماية الأمنية: التاجر يرى حركاته هو فقط
    public static function getEloquentQuery(): Builder
    {
        $vendorId = auth()->user()->vendor?->id;

        if (!$vendorId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->where('vendor_id', $vendorId);
    }

    // 🟢 قمنا بحذف دالة form() بالكامل لأننا لا نريد للتاجر أن يضيف حركات يدوياً

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. نوع الحركة (إيداع/سحب) مع أيقونات وألوان توضيحية
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'credit' => 'إيداع (أرباح)',
                        'debit'  => 'سحب (خصم)',
                        default  => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'success', // أخضر للإيداع
                        'debit'  => 'danger',  // أحمر للسحب
                        default  => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'credit' => 'heroicon-m-arrow-down-left',
                        'debit'  => 'heroicon-m-arrow-up-right',
                        default  => 'heroicon-m-banknotes',
                    }),

                // 2. المبلغ (مع إضافة إشارة + أو - بناءً على النوع)
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SYP')
                    ->weight('bold')
                    ->color(fn ($record) => $record->type === 'credit' ? 'success' : 'danger')
                    ->formatStateUsing(function ($record) {
                        $symbol = $record->type === 'credit' ? '+' : '-';
                        return $symbol . ' ' . number_format($record->amount, 0) . ' SYP';
                    }),

                // 3. وصف الحركة المالي (شرح العملية)
                TextColumn::make('description')
                    ->label('البيان')
                    ->wrap() // لكي لا ينقطع النص الطويل
                    ->limit(50),

                // 4. حالة الحركة
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending'   => 'warning',
                        'failed'    => 'danger',
                        default     => 'gray',
                    }),

                // 5. التاريخ والوقت بدقة
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('d M Y - h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc') // أحدث الحركات تظهر أولاً
            ->filters([
                // فلتر سريع ليفرز التاجر السحوبات عن الأرباح
                SelectFilter::make('type')
                    ->label('نوع الحركة')
                    ->options([
                        'credit' => 'الإيداعات فقط',
                        'debit'  => 'السحوبات فقط',
                    ]),
            ])
            ->actions([]) // لا يوجد أي أزرار (فقط عرض)
            ->bulkActions([]); // منع الحذف الجماعي
    }

    public static function getPages(): array
    {
        return [
            // صفحة العرض (الجدول) فقط لا غير!
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}