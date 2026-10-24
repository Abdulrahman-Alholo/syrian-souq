<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Modules\Finance\Models\VendorTransaction;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class TransactionResource extends Resource
{
    protected static ?string $model = VendorTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-Clipboard-document-list';
    protected static ?string $modelLabel = 'قيد مالي';
    protected static ?string $pluralModelLabel = 'السجل المالي العام';
    protected static string|\UnitEnum|null $navigationGroup = 'الماليات';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false; // السجل المالي للقراءة والرقابة فقط (Immutable Audit Log)
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('رقم القيد')
                    ->sortable(),

                TextColumn::make('vendor.store_name')
                    ->label('المتجر')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('الاتجاه')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'credit' ? 'إيداع (+)' : 'خصم (-)')
                    ->color(fn (string $state) => $state === 'credit' ? 'success' : 'danger'),

                TextColumn::make('transaction_type')
                    ->label('نوع العملية')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'order_earning'     => 'أرباح طلبية مسلّمة',
                        'payout'            => 'سحب أرباح نقدي',
                        'manual_adjustment' => 'تسوية إدارية',
                        default             => (string) $state,
                    }),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SYP')
                    ->weight('bold')
                    ->color(fn (VendorTransaction $record) => $record->type === 'credit' ? 'success' : 'danger'),

                TextColumn::make('balance_after')
                    ->label('رصيد المتجر بعد الحركة')
                    ->money('SYP')
                    ->color('info'),

                TextColumn::make('description')
                    ->label('البيان والتفاصيل')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label('التوقيت الدقيق')
                    ->dateTime('d M Y - h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('vendor_id')
                    ->relationship('vendor', 'store_name')
                    ->label('تصفية حسب المتجر'),

                SelectFilter::make('type')
                    ->label('نوع القيد')
                    ->options([
                        'credit' => 'إيداعات (+)',
                        'debit'  => 'خصومات وسحوبات (-)',
                    ]),

                SelectFilter::make('transaction_type')
                    ->label('تصنيف العملية')
                    ->options([
                        'order_earning'     => 'أرباح مبيعات',
                        'payout'            => 'سحوبات التجار',
                        'manual_adjustment' => 'تسويات إدارية',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}