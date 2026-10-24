<?php

namespace App\Filament\Vendor\Resources;

use App\Filament\Vendor\Resources\OrderResource\Pages;
use App\Modules\Order\Models\VendorOrder;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;

use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;

class OrderResource extends Resource
{
    protected static ?string $model = VendorOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $modelLabel = 'طلب وارد';
    protected static ?string $pluralModelLabel = 'الطلبات الواردة';
    protected static string|\UnitEnum|null $navigationGroup = 'المبيعات والكتالوج';
    protected static ?int $navigationSort = 1;

    // 🔒 منع التاجر من إنشاء طلبات يدوياً أو حذفها
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * 🔔 شارة تنبيه بعدد الطلبات الجديدة التي تنتظر التجهيز من هذا التاجر
     */
    public static function getNavigationBadge(): ?string
    {
        $vendorId = auth()->user()?->vendor?->id;
        if (!$vendorId) {
            return null;
        }

        $count = static::getModel()::where('vendor_id', $vendorId)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // 🛡️ عزل البيانات: التاجر يرى شحناته هو فقط مع تحميل العلاقات مسبقاً (Eager Loading)
    public static function getEloquentQuery(): Builder
    {
        $vendorId = auth()->user()?->vendor?->id;

        if (!$vendorId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('vendor_id', $vendorId)
            ->with(['order', 'items']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            // 1. بطاقة معلومات الشحنة وتحديث الحالة
            Section::make('بطاقة الشحنة وتحديث الحالة')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('custom_order_number')
                            ->label('رقم الطلب المرجعي')
                            ->disabled()
                            ->formatStateUsing(fn (?VendorOrder $record) => $record?->order?->order_number ?? 'غير متوفر'),

                        TextInput::make('shipment_id')
                            ->label('معرف الشحنة الفرعية')
                            ->disabled()
                            ->formatStateUsing(fn (?VendorOrder $record) => $record ? 'SHP-#' . $record->id : '-'),

                        TextInput::make('created_at_display')
                            ->label('تاريخ ورود الطلب')
                            ->disabled()
                            ->formatStateUsing(fn (?VendorOrder $record) => $record?->created_at?->format('Y-m-d h:i A') ?? '-'),

                        Select::make('status')
                            ->label('تحديث حالة التجهيز')
                            ->options(function (?VendorOrder $record) {
                                $allStatuses = [
                                    'pending'         => 'بانتظار التأكيد',
                                    'processing'      => 'قيد التجهيز والتغليف',
                                    'shipped'         => 'جاهز للشحن / تم التسليم للمندوب',
                                    'delivered'       => 'تم التسليم للعميل (مكتمل)',
                                    'failed_delivery' => 'فشل التوصيل',
                                    'cancelled'       => 'ملغي',
                                    'returned'        => 'مرتجع',
                                ];

                                if (!$record) {
                                    return ['pending' => 'بانتظار التأكيد'];
                                }

                                $allowedKeys = $record->getValidNextStatuses(false);

                                $options = [$record->status => $allStatuses[$record->status] ?? $record->status];
                                foreach ($allowedKeys as $key) {
                                    $options[$key] = $allStatuses[$key] ?? $key;
                                }

                                return $options;
                            })
                            ->required()
                            ->native(false)
                            ->disabled(fn (?VendorOrder $record) =>
                                $record && in_array($record->status, ['delivered', 'cancelled', 'returned', 'failed_delivery'])
                            )
                            ->helperText('عند تجهيز البضاعة وتسليمها لمندوب التوصيل، حول الحالة إلى (جاهز للشحن).'),
                    ]),
                ]),

            // 2. بيانات العميل وعنوان التوصيل (Packing Slip Info)
            Section::make('بيانات العميل وعنوان التوصيل')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('customer_name')
                            ->label('اسم المستلم')
                            ->disabled()
                            ->formatStateUsing(fn (?VendorOrder $record) => $record?->order?->shipping_name ?? 'غير متوفر'),

                        TextInput::make('customer_phone')
                            ->label('رقم الهاتف للتواصل')
                            ->disabled()
                            ->formatStateUsing(fn (?VendorOrder $record) => $record?->order?->shipping_phone ?? 'غير متوفر'),

                        TextInput::make('customer_city')
                            ->label('المدينة / المحافظة')
                            ->disabled()
                            ->formatStateUsing(fn (?VendorOrder $record) => $record?->order?->shipping_city ?? 'غير متوفر'),
                    ]),

                    Grid::make(2)->schema([
                        Textarea::make('customer_address')
                            ->label('العنوان التفصيلي')
                            ->rows(2)
                            ->disabled()
                            ->formatStateUsing(fn (?VendorOrder $record) => $record?->order?->shipping_address_line ?? 'غير متوفر'),

                        Textarea::make('customer_notes')
                            ->label('ملاحظات العميل على الطلب')
                            ->rows(2)
                            ->disabled()
                            ->formatStateUsing(fn (?VendorOrder $record) => $record?->order?->notes ?: 'لا توجد ملاحظات خاصة من العميل'),
                    ]),
                ]),

            // 3. قائمة المنتجات المطلوبة للتجهيز والتغليف
            Section::make('المنتجات المطلوبة للتجهيز من مستودعك')
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->label('')
                        ->schema([
                            Grid::make(5)->schema([
                                TextInput::make('product_name')
                                    ->label('اسم المنتج')
                                    ->disabled()
                                    ->columnSpan(2),

                                TextInput::make('variant_description')
                                    ->label('الخصائص المطلوبة (المقاس / اللون)')
                                    ->disabled(),

                                TextInput::make('quantity')
                                    ->label('الكمية المطلوبة')
                                    ->suffix('قطعة')
                                    ->disabled(),

                                TextInput::make('unit_price')
                                    ->label('سعر الوحدة')
                                    ->numeric()
                                    ->suffix('SYP')
                                    ->disabled(),
                            ]),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull()
                        ->disabled(),
                ]),

            // 4. كشف التسوية المالية للطلبية
            Section::make('التسوية المالية وأرباحك الصافية من هذه الشحنة')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('subtotal')
                            ->label('إجمالي قيمة بضاعتك')
                            ->numeric()
                            ->suffix('SYP')
                            ->disabled(),

                        TextInput::make('commission_rate')
                            ->label('نسبة عمولة المنصة')
                            ->suffix('%')
                            ->disabled(),

                        TextInput::make('commission_amount')
                            ->label('قيمة العمولة المقتطعة')
                            ->numeric()
                            ->suffix('SYP')
                            ->disabled(),

                        TextInput::make('vendor_earnings')
                            ->label('صافي ربحك (يُودع فور التسليم)')
                            ->numeric()
                            ->suffix('SYP')
                            ->disabled()
                            ->extraInputAttributes([
                                'style' => 'color: #10b981; font-weight: bold; font-size: 1.05rem;',
                            ]),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->description(fn (VendorOrder $record) => 'شحنة فرعية #' . $record->id),

                TextColumn::make('order.shipping_name')
                    ->label('العميل المستلم')
                    ->searchable()
                    ->description(fn (VendorOrder $record) => '📍 ' . ($record->order?->shipping_city ?? '-') . ' | 📞 ' . ($record->order?->shipping_phone ?? '-')),

                TextColumn::make('items_count')
                    ->label('الأصناف')
                    ->counts('items')
                    ->badge()
                    ->color('gray')
                    ->suffix(' صنف'),

                TextColumn::make('subtotal')
                    ->label('قيمة المبيعات')
                    ->money('SYP')
                    ->sortable(),

                TextColumn::make('commission_amount')
                    ->label('عمولة المنصة')
                    ->money('SYP')
                    ->color('gray'),

                TextColumn::make('vendor_earnings')
                    ->label('صافي ربحك')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color('success'),

                TextColumn::make('status')
                    ->label('حالة الشحنة')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'         => 'بانتظار التأكيد',
                        'processing'      => 'قيد التجهيز',
                        'shipped'         => 'تم التسليم للمندوب',
                        'delivered'       => 'تم التسليم للعميل',
                        'failed_delivery' => 'فشل التوصيل',
                        'returned'        => 'مرتجع',
                        'cancelled'       => 'ملغي',
                        default           => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'                              => 'warning',
                        'processing'                           => 'info',
                        'shipped'                              => 'primary',
                        'delivered'                            => 'success',
                        'cancelled', 'failed_delivery', 'returned' => 'danger',
                        default                                => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('d M Y - h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('تصفية حسب حالة الشحنة')
                    ->options([
                        'pending'    => 'بانتظار التأكيد',
                        'processing' => 'قيد التجهيز (مطلوب تحضيرها)',
                        'shipped'    => 'تم التسليم للمندوب',
                        'delivered'  => 'تم التسليم (مكتملة)',
                        'cancelled'  => 'ملغاة',
                    ]),
            ])
            ->recordUrl(fn (VendorOrder $record): string =>
                static::getUrl('edit', ['record' => $record])
            )
            ->actions([
                EditAction::make()
                    ->label('بطاقة التجهيز')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('gray'),

                Action::make('updateStatus')
                    ->label('تحديث الحالة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->button()
                    ->size('sm')
                    ->fillForm(fn (VendorOrder $record): array => [
                        'status' => $record->status,
                    ])
                    ->form([
                        Select::make('status')
                            ->label('الانتقال إلى المرحلة التالية')
                            ->options(function (VendorOrder $record) {
                                $allStatuses = [
                                    'pending'         => 'بانتظار التأكيد',
                                    'processing'      => 'قيد التجهيز والتغليف',
                                    'shipped'         => 'جاهز للشحن / تم التسليم للمندوب',
                                    'delivered'       => 'تم التسليم بنجاح (مكتمل)',
                                    'failed_delivery' => 'فشل التوصيل',
                                    'cancelled'       => 'ملغي',
                                    'returned'        => 'مرتجع',
                                ];

                                $allowedKeys = $record->getValidNextStatuses(false);

                                $options = [$record->status => $allStatuses[$record->status] ?? $record->status];
                                foreach ($allowedKeys as $key) {
                                    $options[$key] = $allStatuses[$key] ?? $key;
                                }
                                return $options;
                            })
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data, VendorOrder $record): void {
                        try {
                            if ($record->status === $data['status']) {
                                return;
                            }

                            $record->update(['status' => $data['status']]);

                            Notification::make()
                                ->success()
                                ->title('تم تحديث حالة الشحنة بنجاح')
                                ->body("رقم الطلب: {$record->order?->order_number}")
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('غير مسموح بهذا الانتقال')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (VendorOrder $record) =>
                        !in_array($record->status, ['delivered', 'cancelled', 'returned', 'failed_delivery'])
                    ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit'  => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}