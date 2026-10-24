<?php

namespace App\Filament\Vendor\Widgets;

use App\Modules\Order\Models\VendorOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class VendorLatestOrdersWidget extends BaseWidget
{
    protected static ?string $heading = 'أحدث الطلبات الواردة لمتجرك';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $vendorId = auth()->user()?->vendor?->id;

        return $table
            ->query(
                VendorOrder::query()
                    ->where('vendor_id', $vendorId ?? 0)
                    ->with(['order', 'items'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('رقم الطلب')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, VendorOrder $record) => ($state ?? '#' . $record->order_id) . ' (شحنة #' . $record->id . ')'),

                Tables\Columns\TextColumn::make('order.shipping_name')
                    ->label('العميل المستلم')
                    ->description(fn (VendorOrder $record) => '📍 ' . ($record->order?->shipping_city ?? '-')),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('عدد القطع')
                    ->counts('items')
                    ->badge()
                    ->color('gray')
                    ->suffix(' قطع'),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('قيمة البضاعة')
                    ->money('SYP'),

                Tables\Columns\TextColumn::make('vendor_earnings')
                    ->label('صافي ربحك')
                    ->money('SYP')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('حالة الشحنة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'         => 'قيد الانتظار',
                        'processing'      => 'بانتظار تجهيزك',
                        'shipped'         => 'تم التسليم للمندوب',
                        'delivered'       => 'تم التسليم للعميل',
                        'cancelled'       => 'ملغي',
                        'returned'        => 'مرتجع',
                        'failed_delivery' => 'فشل التوصيل',
                        default           => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'                              => 'warning',
                        'processing'                           => 'info',
                        'shipped'                              => 'primary',
                        'delivered'                            => 'success',
                        'cancelled', 'returned', 'failed_delivery' => 'danger',
                        default                                => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('منذ')
                    ->since(),
            ])
            ->paginated(false);
    }
}