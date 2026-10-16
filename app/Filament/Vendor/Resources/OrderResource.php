<?php

namespace App\Filament\Vendor\Resources;

use App\Filament\Vendor\Resources\OrderResource\Pages;
use App\Modules\Order\Models\VendorOrder;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;

use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
class OrderResource extends Resource
{
    protected static ?string $model = VendorOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $modelLabel = 'طلب وارد';
    protected static ?string $pluralModelLabel = 'الطلبات الواردة';
    protected static ?int $navigationSort = 2;

    // 🛡️ التاجر يرى طلباته فقط
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('vendor_id', auth()->user()?->vendor?->id);
    }

    // ✅ FORM = Schema في v5
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('معلومات الطلب')
                ->schema([
                    Grid::make(3)->schema([
                        // 👈 غيرنا الاسم الوهمي واستخدمنا formatStateUsing لجلب الداتا من العلاقة
                        TextInput::make('custom_order_number')
                            ->label('رقم الطلب')
                            ->disabled()
                            ->formatStateUsing(fn (VendorOrder $record) => $record->order?->order_number ?? 'غير متوفر'),

                        TextInput::make('created_at')
                            ->label('التاريخ')
                            ->disabled(),

                        Select::make('status')
                            ->label('تحديث الحالة')
                            // ... (خلي كود الحالة المعقد تبعك متل ما هو هون، مافي مشكلة) ...
                            ->options(function (?VendorOrder $record) {
                                $allStatuses = [
                                    'pending' => 'بانتظار التأكيد',
                                    'processing' => 'قيد التجهيز والتغليف',
                                    'shipped' => 'جاهز للشحن / تم التسليم للمندوب',
                                    'delivered' => 'تم التسليم بنجاح (مكتمل)',
                                    'failed_delivery' => 'فشل التوصيل',
                                    'cancelled' => 'ملغي',
                                    'returned' => 'مرتجع',
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
                            ->helperText('الخيارات المتاحة تعتمد على حالة الطلب الحالية. لا يمكنك تخطي المراحل.'),
                    ]),
                ]),
            
            Section::make('بيانات العميل')
                ->schema([
                    Grid::make(3)->schema([
                        // 👈 السحر الحقيقي هنا!
                        TextInput::make('customer_name')
                            ->label('الاسم')
                            ->disabled()
                            ->formatStateUsing(fn (VendorOrder $record) => $record->order?->shipping_name ?? 'غير متوفر'),
                            
                        TextInput::make('customer_phone')
                            ->label('الهاتف')
                            ->disabled()
                            ->formatStateUsing(fn (VendorOrder $record) => $record->order?->shipping_phone ?? 'غير متوفر'),
                            
                        TextInput::make('customer_city')
                            ->label('المدينة')
                            ->disabled()
                            ->formatStateUsing(fn (VendorOrder $record) => $record->order?->shipping_city ?? 'غير متوفر'),
                    ]),

                    TextInput::make('customer_address')
                        ->label('العنوان')
                        ->columnSpanFull()
                        ->disabled()
                        ->formatStateUsing(fn (VendorOrder $record) => $record->order?->shipping_address_line ?? 'غير متوفر'),
                ]),

            Section::make('المنتجات')
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('product_name')->label('المنتج')->disabled(),
                                TextInput::make('variant_description')->label('النسخة')->disabled(),
                                TextInput::make('quantity')->label('الكمية')->numeric()->disabled(),
                            ]),
                        ])
                        ->columnSpanFull()
                        ->disabled(),
                ]),

            Section::make('الملخص المالي')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('subtotal')->label('القيمة')->disabled(),
                        TextInput::make('commission_amount')->label('العمولة')->disabled(),
                        TextInput::make('vendor_earnings')
                            ->label('ربحك')
                            ->disabled()
                            ->extraInputAttributes([
                                'style' => 'color: green; font-weight: bold;',
                            ]),
                    ]),
                ]),
        ]);
    }

    // ✅ TABLE
    public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('order.order_number')
                ->label('رقم الطلب')
                ->searchable()
                ->sortable(),

            TextColumn::make('order.shipping_name')
                ->label('العميل')
                ->searchable(),

            TextColumn::make('vendor_earnings')
                ->label('أرباحك')
                ->money('SYP')
                ->sortable()
                ->badge()
                ->color('success'),

            TextColumn::make('status')
                ->label('الحالة')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'بانتظار التأكيد',
                    'processing' => 'قيد التجهيز',
                    'shipped' => 'جاهز للشحن',
                    'delivered' => 'تم التسليم',
                    'failed_delivery' => 'فشل التوصيل',
                    'returned' => 'مرتجع',
                    'cancelled' => 'ملغي',
                    default => $state,
                })
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'warning',
                    'processing' => 'info',
                    'shipped' => 'primary',
                    'delivered' => 'success',
                    'cancelled', 'failed_delivery', 'returned' => 'danger',
                    default => 'secondary',
                }),

            TextColumn::make('created_at')
                ->label('التاريخ')
                ->dateTime()
                ->sortable(),
        ])
        ->defaultSort('created_at', 'desc')
        ->recordUrl(fn (VendorOrder $record): string =>
            static::getUrl('edit', ['record' => $record])
        )

        ->actions([
            Action::make('updateStatus')
                ->label('تحديث الحالة')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->button()
                ->size('sm') // 👈 هذا هو السحر الجديد بـ Filament v5
                ->form([
                    Select::make('status')
                        ->label('حالة الطلب الجديدة')
                        ->options(function (VendorOrder $record) {
                            $allStatuses = [
                                'pending' => 'بانتظار التأكيد',
                                'processing' => 'قيد التجهيز والتغليف',
                                'shipped' => 'جاهز للشحن / تم التسليم للمندوب',
                                'delivered' => 'تم التسليم بنجاح (مكتمل)',
                                'failed_delivery' => 'فشل التوصيل',
                                'cancelled' => 'ملغي',
                                'returned' => 'مرتجع',
                            ];

                            $allowedKeys = $record->getValidNextStatuses(false);
                            
                            // إظهار الحالة الحالية + الحالات المسموحة فقط
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
                    // تحديث الحالة
                    $record->update(['status' => $data['status']]);
                    
                    // إظهار الإشعار الأخضر
                    Notification::make()
                        ->success()
                        ->title('تم تحديث حالة الطلب')
                        ->body("رقم الطلب: {$record->order->order_number}")
                        ->send();
                })
                // إخفاء الزر لو الطلب منتهي
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