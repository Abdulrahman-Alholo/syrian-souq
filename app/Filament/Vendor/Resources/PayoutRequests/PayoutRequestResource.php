<?php

namespace App\Filament\Vendor\Resources\PayoutRequests;

use App\Filament\Vendor\Resources\PayoutRequests\Pages;
use App\Modules\Finance\Models\PayoutRequest;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

use Illuminate\Database\Eloquent\Builder;

class PayoutRequestResource extends Resource
{
    protected static ?string $model = PayoutRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wallet';
    protected static ?string $modelLabel = 'طلب سحب';
    protected static ?string $pluralModelLabel = 'سحب الأرباح';
    protected static string|\UnitEnum|null $navigationGroup = 'الماليات';

    protected static ?int $navigationSort = 2;

    /**
     * 🧠 دالة مساعدة لحساب الرصيد المتاح فعلياً للسحب (بعد استثناء الطلبات المعلقة)
     */
    public static function getAvailableBalance(): float
    {
        $vendor = auth()->user()?->vendor;
        if (!$vendor) return 0;

        $pendingAmount = PayoutRequest::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount');

        return max(0, (float) $vendor->wallet_balance - (float) $pendingAmount);
    }

    public static function getEloquentQuery(): Builder
    {
        $vendorId = auth()->user()?->vendor?->id;

        if (!$vendorId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->where('vendor_id', $vendorId);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل طلب السحب')->schema([
                TextInput::make('amount')
                    ->label('المبلغ المطلوب سحبه')
                    ->numeric()
                    ->required()
                    ->minValue(1000)
                    ->maxValue(fn () => self::getAvailableBalance())
                    ->helperText(fn () => 'رصيدك القابل للسحب حالياً: ' . number_format(self::getAvailableBalance(), 0) . ' SYP (بعد حجز الطلبات قيد المراجعة)')
                    ->suffix('SYP'),

                TextInput::make('bank_details')
                    ->label('تفاصيل التحويل (اسم البنك / رقم الحساب أو الهرم)')
                    ->required()
                    ->columnSpanFull(),

                Textarea::make('admin_notes')
                    ->label('ملاحظات الإدارة (سبب الرفض إن وجد)')
                    ->disabled()
                    ->visible(fn (?PayoutRequest $record) => $record !== null && !empty($record->admin_notes))
                    ->columnSpanFull(),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('رقم الطلب'),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SYP')
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'    => 'قيد المراجعة',
                        'processing' => 'جاري التحويل',
                        'approved'   => 'مكتمل',
                        'rejected'   => 'مرفوض',
                        'failed'     => 'فشل',
                        default      => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'    => 'warning',
                        'processing' => 'info',
                        'approved'   => 'success',
                        'rejected'   => 'danger',
                        'failed'     => 'danger',
                        default      => 'gray',
                    }),

                TextColumn::make('admin_notes')
                    ->label('ملاحظات الإدارة')
                    ->limit(30)
                    ->placeholder('-')
                    ->tooltip(fn ($record) =>
                        strlen((string) $record->admin_notes) > 30 ? $record->admin_notes : null
                    ),

                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('d M Y - h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make()->label('التفاصيل'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPayoutRequests::route('/'),
            'create' => Pages\CreatePayoutRequest::route('/create'),
        ];
    }
}