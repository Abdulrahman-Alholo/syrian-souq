<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages;
use App\Modules\Order\Models\Order;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Actions\ViewAction;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use App\Modules\Order\Models\VendorOrder;
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $modelLabel = 'طلب عميل';
    protected static ?string $pluralModelLabel = 'طلبات العملاء';

    // ✅ FORM (في v5 = Schema)
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('معلومات العميل والشحن')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('order_number')->label('رقم الطلب')->disabled(),
                        TextInput::make('shipping_name')->label('اسم المستلم')->disabled(),
                        TextInput::make('shipping_phone')->label('رقم الهاتف')->disabled(),
                        TextInput::make('shipping_city')->label('المدينة')->disabled(),
                        TextInput::make('payment_method')->label('طريقة الدفع')->disabled(),

                        Select::make('status')
                            ->label('حالة الطلب')
                            ->options([
                                'pending'           => 'قيد الانتظار',
                                'processing'        => 'قيد التجهيز',
                                'partially_shipped' => 'شُحن جزئياً',
                                'shipped'           => 'تم الشحن',
                                'delivered'         => 'تم التسليم (مكتمل)', // 👈 بدلاً من completed
                                'cancelled'         => 'ملغي',
                                'returned'          => 'مرتجع',
                                'failed_delivery'   => 'فشل التوصيل',
                            ])
                            ->disabled(),
                    ]),

                    TextInput::make('shipping_address_line')
                        ->label('العنوان')
                        ->columnSpanFull()
                        ->disabled(),
                ]),

            // 🧠 Vendor Orders
            Section::make('تقسيم الطلب على المتاجر')
                ->schema([
                    Repeater::make('vendorOrders')
                        ->relationship('vendorOrders')
                        ->label('')
                        ->schema([
                            Grid::make(4)->schema([

                                TextInput::make('store_name_display')
                                    ->label('المتجر')
                                    ->disabled()
                                    ->formatStateUsing(fn (?VendorOrder $record) => $record?->vendor?->store_name ?? 'غير محدد'),

                                TextInput::make('subtotal')
                                    ->label('القيمة')
                                    ->numeric()
                                    ->disabled(),

                                TextInput::make('vendor_earnings')
                                    ->label('ربح التاجر')
                                    ->numeric()
                                    ->disabled(),

                                TextInput::make('status')
                                    ->label('الحالة')
                                    ->disabled(),
                            ]),
                        ])
                        ->columnSpanFull()
                        ->disabled(),
                ]),

            Section::make('الإجماليات')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('subtotal')->label('المجموع')->disabled(),
                        TextInput::make('shipping_price')->label('الشحن')->disabled(),
                        TextInput::make('total_price')->label('الإجمالي')->disabled(),
                    ]),
                ]),
        ]);
    }

    // ✅ TABLE
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('shipping_name')
                    ->label('العميل')
                    ->searchable(),

                TextColumn::make('total_price')
                    ->label('الإجمالي')
                    ->money('SYP')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('payment_method')
                    ->label('الدفع')
                    ->badge(),

                // استبدل SelectColumn بهذا الـ TextColumn في جدول الأدمن:
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
                        'pending'           => 'warning',
                        'processing', 'partially_shipped' => 'info',
                        'shipped'           => 'primary',
                        'delivered'         => 'success',
                        'cancelled', 'returned', 'failed_delivery' => 'danger',
                        default             => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
          ->actions([
                ViewAction::make(),
                
                \Filament\Actions\Action::make('updateVendorOrdersStatus')
                    ->label('تحديث حالة التوصيل')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->fillForm(fn (Order $record): array => [
                        'new_status' => $record->vendorOrders()->first()?->status ?? 'shipped',
                    ])
                    ->form([
                        Select::make('new_status')
                            ->label('تحديد حالة التوصيل لمتاجر هذا الطلب')
                            ->options([
                                'processing'      => 'قيد التجهيز',
                                'shipped'         => 'تم الشحن (مع المندوب)',
                                'delivered'       => '✅ تم التسليم للعميل (تحرير الأرباح)',
                                'failed_delivery' => '❌ فشل التوصيل',
                                'returned'        => '🔄 مرتجع',
                                'cancelled'       => '🚫 ملغي',
                            ])
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data, Order $record): void {
                        $newStatus = $data['new_status'];
                        $updatedCount = 0;

                        // جلب الطلبات الفرعية وتحديثها واحداً تلو الآخر لتشغيل الـ Observer والـ Guard
                        foreach ($record->vendorOrders()->get() as $vo) {
                            if ($vo->status !== $newStatus) {
                                $vo->status = $newStatus;
                                $vo->save(); // save() يضمن تشغيل updating و updated في المودل والـ Observer
                                $updatedCount++;
                            }
                        }

                        // تأكيد مزامنة الطلب الأب فوراً حتى لو كان مكتوباً completed بالخطأ سابقاً
                        app(\App\Modules\Order\Services\OrderWorkflowService::class)
                            ->syncParentOrderStatus($record->id);

                        if ($updatedCount > 0) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title("تم تحديث {$updatedCount} طلب فرعي إلى [{$newStatus}] وتحرير الأرباح!")
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('الحالة مختارة مسبقاً، تم فقط مزامنة الطلب العام.')
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