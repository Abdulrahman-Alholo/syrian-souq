<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorResource\Pages;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Finance\Models\WalletAdjustment;
use App\Modules\Finance\Services\LedgerService;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $modelLabel = 'متجر شريك';
    protected static ?string $pluralModelLabel = 'المتاجر الشريكة';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المتاجر';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', 'pending')->count();
        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('البيانات الأساسية للمتجر')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('store_name')
                            ->label('اسم المتجر')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('store_slug')
                            ->label('المعرف النصي للرابط (Slug)')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('city')
                            ->label('المدينة / المحافظة')
                            ->required(),

                        TextInput::make('phone')
                            ->label('هاتف التواصل للمتجر'),
                    ]),

                    Textarea::make('description')
                        ->label('وصف المتجر ونبذة عنه')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('الحوكمة والسياسة المالية (خاص بالإدارة العليا)')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('commission_rate')
                            ->label('نسبة عمولة المنصة من المبيعات')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.5)
                            ->suffix('%')
                            ->required()
                            ->helperText('تُقتطع تلقائياً من كل طلبية جديدة لهذا المتجر'),

                        Select::make('status')
                            ->label('حالة اعتماد المتجر')
                            ->options([
                                'pending'  => 'قيد المراجعة (بانتظار الاعتماد)',
                                'approved' => 'معتمد ونشط (Approved)',
                                'rejected' => 'مرفوض / موقوف (Suspended)',
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('wallet_balance')
                            ->label('الرصيد الحالي في المحفظة')
                            ->numeric()
                            ->suffix('SYP')
                            ->disabled()
                            ->helperText('لتعديل الرصيد استخدم زر التسوية المالية من الجدول الخارجي'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store_name')
                    ->label('اسم المتجر')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Vendor $record) => 'المالك: ' . ($record->user->name ?? 'غير محدد') . ' (' . ($record->user->email ?? '-') . ')'),

                TextColumn::make('city')
                    ->label('المدينة')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('wallet_balance')
                    ->label('الرصيد المتاح')
                    ->money('SYP')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('transactions_count')
                    ->label('حركات السجل')
                    ->counts('transactions')
                    ->badge()
                    ->color('info'),

                TextColumn::make('commission_rate')
                    ->label('نسبة العمولة')
                    ->numeric(2)
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'معتمد ونشط',
                        'pending'  => 'بانتظار التفعيل',
                        'rejected' => 'موقوف / مرفوض',
                        default    => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending'  => 'warning',
                        'rejected' => 'danger',
                        default    => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('حالة المتجر')
                    ->options([
                        'approved' => 'معتمد ونشط',
                        'pending'  => 'بانتظار التفعيل',
                        'rejected' => 'موقوف / مرفوض',
                    ]),
            ])
            ->actions([
                EditAction::make()->label('تعديل السياسة'),

                // 🟢 زر تفعيل سريع للمتاجر المعلقة أو الموقوفة
                Action::make('approve_vendor')
                    ->label('اعتماد المتجر')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Vendor $record) => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->action(function (Vendor $record) {
                        $record->update(['status' => 'approved']);
                        Notification::make()->success()->title('تم اعتماد وتفعيل المتجر بنجاح')->send();
                    }),

                // ⚖️ زر التسوية المالية الشامل (إيداع مكافأة أو خصم غرامة)
                Action::make('wallet_adjustment')
                    ->label('تسوية مالية')
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->modalHeading(fn (Vendor $record) => 'تسوية مالية لمحفظة: ' . $record->store_name)
                    ->modalDescription('سيتم توثيق هذه العملية في السجل القانوني ودفتر الأستاذ العام وإشعار التاجر فوراً.')
                    ->form([
                        Select::make('type')
                            ->label('نوع التسوية الإدارية')
                            ->options([
                                'credit' => '➕ إيداع رصيد / مكافأة / تعويض للتاجر',
                                'debit'  => '➖ خصم رصيد / غرامة تأخير / استرداد من التاجر',
                            ])
                            ->default('credit')
                            ->required()
                            ->native(false),

                        TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('SYP'),

                        Textarea::make('reason')
                            ->label('البيان القانوني (سبب التسوية)')
                            ->placeholder('مثال: مكافأة التزام بسرعة الشحن / أو: غرامة إلغاء طلب بعد التأكيد')
                            ->required(),
                    ])
                    ->action(function (Vendor $record, array $data, LedgerService $ledger) {
                        try {
                            $adjustment = WalletAdjustment::create([
                                'vendor_id' => $record->id,
                                'admin_id'  => auth()->id(),
                                'type'      => $data['type'],
                                'amount'    => $data['amount'],
                                'reason'    => $data['reason'],
                            ]);

                            $prefix = $data['type'] === 'credit' ? 'إيداع إداري: ' : 'خصم إداري: ';

                            $ledger->processTransaction(
                                vendorId: $record->id,
                                type: $data['type'],
                                amount: (float) $data['amount'],
                                transactionType: 'manual_adjustment',
                                referenceType: get_class($adjustment),
                                referenceId: $adjustment->id,
                                description: $prefix . $data['reason'],
                                idempotencyKey: 'manual_adj_' . $adjustment->id
                            );

                            Notification::make()
                                ->success()
                                ->title('تمت التسوية المالية بنجاح')
                                ->body('تم تحديث المحفظة وتوثيق القيد المحاسبي.')
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('فشلت العملية')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendors::route('/'),
            'edit'  => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}