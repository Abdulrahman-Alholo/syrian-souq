<?php

namespace App\Filament\Widgets;

use App\Modules\Order\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrdersWidget extends BaseWidget
{
    protected static ?string $heading = 'أحدث طلبات العملاء على المنصة';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('رقم الطلب')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('shipping_name')
                    ->label('العميل'),

                Tables\Columns\TextColumn::make('shipping_city')
                    ->label('المدينة')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('الإجمالي')
                    ->money('SYP')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'delivered', 'completed' => 'success',
                        'processing', 'shipped'  => 'info',
                        'pending'                => 'warning',
                        'cancelled'              => 'danger',
                        default                  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('وقت الطلب')
                    ->since(),
            ])
            ->paginated(false);
    }
}