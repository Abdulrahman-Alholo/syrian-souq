<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorResource\Pages;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Finance\Models\VendorTransaction;

// 🟢 استدعاءات التخطيط والنماذج
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

// 🟢 استدعاءات الجداول (Tables) الصحيحة
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\EditAction;

// 🟢 استدعاءات النظام
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $modelLabel = 'متجر';
    protected static ?string $pluralModelLabel = 'المتاجر';

    // 1. هيكل الجدول (Table)
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store_name')
                    ->label('اسم المتجر')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => 'المالك: ' . ($record->user->name ?? 'غير محدد')),

                TextColumn::make('city')
                    ->label('المدينة')
                    ->badge()
                    ->color('gray'),

                // 🟢 عرض الرصيد المالي بشكل بارز
                TextColumn::make('wallet_balance')
                    ->label('الرصيد المتاح')
                    ->money('SYP')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),

                // 🔵 إحصائيات نشاط التاجر (عدد الحركات المالية)
                TextColumn::make('transactions_count')
                    ->label('حركات السجل')
                    ->counts('transactions')
                    ->badge()
                    ->color('info')
                    ->tooltip('عدد الإيداعات والسحوبات في كشف حساب التاجر'),

                TextColumn::make('commission_rate')
                    ->label('العمولة')
                    ->numeric(2)
                    ->suffix('%'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending'  => 'warning',
                        'rejected' => 'danger',
                        default    => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // الفلاتر هنا
            ])
            ->actions([
                // زر التعديل الصحيح الخاص بالجداول
                EditAction::make(),

                // 🔥 الزر الاحترافي والمستحيل الكسر
                Action::make('manual_deposit')
                        ->label('إيداع مكافأة/رصيد')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('إيداع رصيد في حساب التاجر')
                        ->modalDescription('سيتم إضافة هذا المبلغ وتوثيقه في السجل المالي بشكل قطعي.')
                        ->form([
                            TextInput::make('amount')
                                ->label('المبلغ المراد إيداعه')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->suffix('SYP'),
                            
                            Textarea::make('reason')
                                ->label('البيان (سبب الإيداع)')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                // 1. إنشاء المستند القانوني (التسوية) أولاً
                                $adjustment = \App\Modules\Finance\Models\WalletAdjustment::create([
                                    'vendor_id' => $record->id,
                                    'admin_id'  => auth()->id(),
                                    'type'      => 'credit',
                                    'amount'    => $data['amount'],
                                    'reason'    => $data['reason'],
                                ]);

                                // 2. نداء المحرك المالي المركزي (LedgerService)
                                $ledger = app(\App\Modules\Finance\Services\LedgerService::class);
                                
                                $ledger->processTransaction(
                                    vendorId: $record->id,
                                    type: 'credit',
                                    amount: $data['amount'],
                                    transactionType: 'manual_adjustment',
                                    referenceType: get_class($adjustment),
                                    referenceId: $adjustment->id,
                                    description: 'إيداع إداري: ' . $data['reason'],
                                    // 🛡️ مفتاح الحماية: كلمة + رقم التسوية (يستحيل أن يتكرر)
                                    idempotencyKey: 'manual_adj_' . $adjustment->id 
                                );

                                Notification::make()
                                    ->success()
                                    ->title('تم الإيداع بنجاح')
                                    ->body('تمت العملية بسلامة مالية مطلقة.')
                                    ->send();
                                    
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('فشل الإيداع')
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),
            ]);
    }

    // 2. هيكل الفورم (Form)
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('store_name')->required(),
                TextInput::make('store_slug')->required(),
                Textarea::make('description'),
                TextInput::make('city'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendors::route('/'),
            'edit' => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}