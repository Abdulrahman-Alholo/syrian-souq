<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\VendorOrder;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $modelLabel = 'طلب عميل';
    protected static ?string $pluralModelLabel = 'طلبات العملاء';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المبيعات والكتالوج';
    protected static ?int $navigationSort = 1;

    /**
     * 🔔 إظهار عدد الطلبات الجديدة (قيد الانتظار أو التجهيز) بجانب القائمة الجانبية
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', ['pending', 'processing'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            // 1. بيانات العميل والشحن والدفع
            Section::make('معلومات العميل والشحن')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('order_number')
                            ->label('رقم الطلب المرجعي')
                            ->disabled(),

                        TextInput::make('shipping_name')
                            ->label('اسم المستلم')
                            ->disabled(),

                        TextInput::make('shipping_phone')
                            ->label('رقم الهاتف')
                            ->disabled(),

                        TextInput::make('shipping_city')
                            ->label('المدينة')
                            ->disabled(),

                        TextInput::make('payment_method')
                            ->label('طريقة الدفع')
                            ->formatStateUsing(fn (?string $state) => match ($state) {
                                'cod'    => 'الدفع عند الاستلام (COD)',
                                'stripe' => 'بطاقة إلكترونية (Stripe)',
                                default  => (string) $state,
                            })
                            ->disabled(),

                        TextInput::make('paid_at')
                            ->label('تاريخ التحصيل المالي')
                            ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d h:i A') : 'لم يُحصّل بعد')
                            ->disabled(),

                        Select::make('status')
                            ->label('حالة الطلب العام')
                            ->options([
                                'pending'           => 'قيد الانتظار',
                                'processing'        => 'قيد التجهيز',
                                'partially_shipped' => 'شُحن جزئياً',
                                'shipped'           => 'تم الشحن',
                                'delivered'         => 'تم التسليم (مكتمل)',
                                'cancelled'         => 'ملغي',
                                'returned'          => 'مرتجع',
                                'failed_delivery'   => 'فشل التوصيل',
                            ])
                            ->disabled()
                            ->columnSpan(2),
                    ]),

                    Grid::make(2)->schema([
                        Textarea::make('shipping_address_line')
                            ->label('العنوان التفصيلي للتوصيل')
                            ->rows(2)
                            ->disabled(),

                        Textarea::make('notes')
                            ->label('ملاحظات العميل على الطلب')
                            ->placeholder('لا توجد ملاحظات')
                            ->rows(2)
                            ->disabled(),
                    ]),
                ]),

            // 2. 📦 جدول المنتجات التفصيلي داخل الطلب (كان مفقوداً!)
            Section::make('المنتجات المطلوبة في سلة العميل (Order Items)')
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
                                    ->label('الخصائص (اللون / المقاس)')
                                    ->disabled(),

                                TextInput::make('quantity')
                                    ->label('الكمية')
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

            // 3. 🏪 تقسيم الشحنات والمحاسبة المالية للمتاجر
            Section::make('تقسيم الشحنات وتوزيع الأرباح والعمولات على المتاجر (Vendor Orders)')
                ->schema([
                    Repeater::make('vendorOrders')
                        ->relationship('vendorOrders')
                        ->label('')
                        ->schema([
                            Grid::make(6)->schema([
                                TextInput::make('store_name_display')
                                    ->label('المتجر الشريك')
                                    ->disabled()
                                    ->formatStateUsing(fn (?VendorOrder $record) => $record?->vendor?->store_name ?? 'غير محدد'),

                                TextInput::make('subtotal')
                                    ->label('إجمالي حصة المتجر')
                                    ->numeric()
                                    ->suffix('SYP')
                                    ->disabled(),

                                TextInput::make('commission_rate')
                                    ->label('نسبة العمولة')
                                    ->suffix('%')
                                    ->disabled(),

                                TextInput::make('commission_amount')
                                    ->label('عمولة المنصة الصافية')
                                    ->numeric()
                                    ->suffix('SYP')
                                    ->disabled(),

                                TextInput::make('vendor_earnings')
                                    ->label('صافي ربح التاجر')
                                    ->numeric()
                                    ->suffix('SYP')
                                    ->disabled(),

                                TextInput::make('status_display')
                                    ->label('حالة الشحنة')
                                    ->disabled()
                                    ->formatStateUsing(fn (?VendorOrder $record) => match ($record?->status) {
                                        'pending'         => 'قيد الانتظار',
                                        'processing'      => 'قيد التجهيز عند التاجر',
                                        'shipped'         => 'تم الشحن مع المندوب',
                                        'delivered'       => 'تم التسليم للعميل',
                                        'cancelled'       => 'ملغي',
                                        'returned'        => 'مرتجع',
                                        'failed_delivery' => 'فشل التوصيل',
                                        default           => $record?->status ?? '-',
                                    }),
                            ]),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull()
                        ->disabled(),
                ]),

            // 4. 💰 الملخص المالي النهائي للفاتورة
            Section::make('ملخص الفاتورة النهائية')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('subtotal')
                            ->label('مجموع المنتجات')
                            ->numeric()
                            ->suffix('SYP')
                            ->disabled(),

                        TextInput::make('shipping_price')
                            ->label('أجور الشحن')
                            ->numeric()
                            ->suffix('SYP')
                            ->disabled(),

                        TextInput::make('discount')
                            ->label('قيمة الخصم')
                            ->numeric()
                            ->suffix('SYP')
                            ->disabled(),

                        TextInput::make('total_price')
                            ->label('الإجمالي النهائي المطلوب')
                            ->numeric()
                            ->suffix('SYP')
                            ->disabled(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('shipping_name')
                    ->label('العميل المستلم')
                    ->searchable()
                    ->description(fn (Order $record) => '📞 ' . $record->shipping_phone . ' | 📍 ' . $record->shipping_city),

                TextColumn::make('vendor_orders_count')
                    ->label('عدد الشحنات')
                    ->counts('vendorOrders')
                    ->badge()
                    ->color('gray')
                    ->suffix(' متجر'),

                TextColumn::make('total_price')
                    ->label('إجمالي الفاتورة')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                // 💎 عمود يعرض للأدمن فوراً كم ربحت المنصة من هذا الطلب!
                TextColumn::make('vendor_orders_sum_commission_amount')
                    ->label('عمولة المنصة')
                    ->sum('vendorOrders', 'commission_amount')
                    ->money('SYP')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('payment_method')
                    ->label('الدفع')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->color(fn (string $state) => $state === 'cod' ? 'warning' : 'info'),

                TextColumn::make('status')
                    ->label('حالة الطلب العام')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'           => 'قيد الانتظار',
                        'processing'        => 'قيد التجهيز',
                        'partially_shipped' => 'شُحن جزئياً',
                        'shipped'           => 'تم الشحن',
                        'delivered'         => 'تم التسليم (مكتمل)',
                        'cancelled'         => 'ملغي',
                        'returned'          => 'مرتجع',
                        'failed_delivery'   => 'فشل التوصيل',
                        default             => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'                              => 'warning',
                        'processing', 'partially_shipped'      => 'info',
                        'shipped'                              => 'primary',
                        'delivered'                            => 'success',
                        'cancelled', 'returned', 'failed_delivery' => 'danger',
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
                    ->label('تصفية حسب الحالة')
                    ->options([
                        'pending'           => 'قيد الانتظار',
                        'processing'        => 'قيد التجهيز',
                        'partially_shipped' => 'شُحن جزئياً',
                        'shipped'           => 'تم الشحن',
                        'delivered'         => 'تم التسليم (مكتمل)',
                        'cancelled'         => 'ملغي',
                    ]),

                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cod'    => 'الدفع عند الاستلام (COD)',
                        'stripe' => 'بطاقة إلكترونية (Stripe)',
                    ]),
            ])
            ->actions([
                ViewAction::make()->label('الفاتورة والتفاصيل'),

                Action::make('updateVendorOrdersStatus')
                    ->label('تحديث التوصيل')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn (Order $record) => !in_array($record->status, ['delivered', 'cancelled', 'returned']))
                    ->fillForm(fn (Order $record): array => [
                        'new_status' => $record->vendorOrders()->first()?->status ?? 'shipped',
                    ])
                    ->form([
                        Select::make('new_status')
                            ->label('تحديث حالة الشحنات التابعة لهذا الطلب')
                            ->options([
                                'processing'      => 'قيد التجهيز',
                                'shipped'         => 'تم الشحن (مع المندوب)',
                                'delivered'       => '✅ تم التسليم للعميل (تحرير الأرباح للمحافظ)',
                                'failed_delivery' => '❌ فشل التوصيل (فك حجز المخزون)',
                                'returned'        => '🔄 مرتجع',
                                'cancelled'       => '🚫 إلغاء الطلب',
                            ])
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data, Order $record): void {
                        try {
                            $newStatus = $data['new_status'];
                            $updatedCount = 0;

                            foreach ($record->vendorOrders()->get() as $vo) {
                                if ($vo->status !== $newStatus) {
                                    // إذا اختار الأدمن التسليم المباشر وكان الطلب لا يزال في التجهيز، نمرره بـ shipped أولاً لاحترام الـ State Machine
                                    if ($newStatus === 'delivered' && $vo->status === 'processing') {
                                        $vo->status = 'shipped';
                                        $vo->save();
                                    }

                                    $vo->status = $newStatus;
                                    $vo->save();
                                    $updatedCount++;
                                }
                            }

                            app(\App\Modules\Order\Services\OrderWorkflowService::class)
                                ->syncParentOrderStatus($record->id);

                            if ($newStatus === 'delivered' && $record->payment_method === 'cod') {
                                $record->update(['paid_at' => now()]);
                            }

                            Notification::make()
                                ->success()
                                ->title("تم تحديث {$updatedCount} شحنة إلى حالة [{$newStatus}] بنجاح!")
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('تعذر تغيير الحالة')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view'  => Pages\ViewOrder::route('/{record}'),
        ];
    }
}