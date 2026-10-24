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
    protected static string|\UnitEnum|null $navigationGroup = 'الماليات';
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    // 🛡️ الحماية الأمنية: التاجر يرى حركاته هو فقط
    public static function getEloquentQuery(): Builder
    {
        $vendorId = auth()->user()?->vendor?->id;

        if (!$vendorId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->where('vendor_id', $vendorId);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                // 1. نوع الحركة (إيداع/سحب)
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'credit' => 'إيداع (+)',
                        'debit'  => 'خصم / سحب (-)',
                        default  => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit'  => 'danger',
                        default  => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'credit' => 'heroicon-m-arrow-down-left',
                        'debit'  => 'heroicon-m-arrow-up-right',
                        default  => 'heroicon-m-banknotes',
                    }),

                // 2. تصنيف العملية
                TextColumn::make('transaction_type')
                    ->label('التصنيف')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'order_earning'     => 'أرباح مبيعات',
                        'payout'            => 'سحب نقدي',
                        'manual_adjustment' => 'تسوية إدارية',
                        default             => (string) $state,
                    }),

                // 3. المبلغ
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->weight('bold')
                    ->color(fn ($record) => $record->type === 'credit' ? 'success' : 'danger')
                    ->formatStateUsing(function ($record) {
                        $symbol = $record->type === 'credit' ? '+' : '-';
                        return $symbol . ' ' . number_format($record->amount, 0) . ' SYP';
                    }),

                // 4. الرصيد التراكمي بعد الحركة
                TextColumn::make('balance_after')
                    ->label('الرصيد بعد الحركة')
                    ->money('SYP')
                    ->weight('bold')
                    ->color('info'),

                // 5. وصف الحركة المالي
                TextColumn::make('description')
                    ->label('البيان والتفاصيل')
                    ->wrap(),

                // 6. التاريخ والوقت بدقة
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('d M Y - h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('نوع الحركة')
                    ->options([
                        'credit' => 'الإيداعات فقط (+)',
                        'debit'  => 'السحوبات والخصومات فقط (-)',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}