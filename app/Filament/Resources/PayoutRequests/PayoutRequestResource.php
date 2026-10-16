<?php

namespace App\Filament\Resources\PayoutRequests;

use App\Filament\Resources\PayoutRequests\Pages;
use App\Modules\Finance\Models\PayoutRequest;
use App\Modules\Finance\Services\PayoutService;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;


use Filament\Notifications\Notification;
class PayoutRequestResource extends Resource
{
    protected static ?string $model = PayoutRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $modelLabel = 'طلب سحب';
    protected static ?string $pluralModelLabel = 'طلبات السحب';
    protected static string|\UnitEnum|null $navigationGroup = 'الماليات';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // 🟢 استخدام columns(2) مباشرة على الـ Section كبديل آمن عن الـ Grid
            Section::make('تفاصيل طلب السحب')->schema([
                Select::make('vendor_id')
                    ->relationship('vendor', 'store_name')
                    ->label('المتجر')
                    ->disabled(),

                TextInput::make('amount')
                    ->label('المبلغ المطلوب')
                    ->numeric()
                    ->suffix('SYP')
                    ->disabled(),

                TextInput::make('bank_details')
                    ->label('تفاصيل التحويل')
                    ->columnSpanFull()
                    ->disabled(),

                Textarea::make('admin_notes')
                    ->label('ملاحظات الإدارة')
                    ->columnSpanFull()
                    ->disabled(),
            ])->columns(2) 
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('رقم الطلب')->sortable(),

                TextColumn::make('vendor.store_name')
                    ->label('المتجر')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold'),

                // 🟢 عرض تفاصيل البنك مباشرة لتوفير زر "عرض"
                TextColumn::make('bank_details')
                    ->label('تفاصيل التحويل')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->bank_details),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'    => 'قيد المراجعة',
                        'processing' => 'جاري التحويل',
                        'approved'   => 'مكتمل',
                        'rejected'   => 'مرفوض',
                        'failed'     => 'فشل التحويل',
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

                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('d M Y - h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                // 🟢 زر الموافقة المربوط بالـ Service
                \Filament\Actions\Action::make('approve')
                    ->label('موافقة وخصم')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PayoutRequest $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد الموافقة على السحب')
                    ->modalDescription('هل قمت بتحويل المبلغ للتاجر؟ سيتم الخصم نهائياً من رصيده.')
                    ->action(function (PayoutRequest $record, PayoutService $service) {
                        try {
                            $service->approvePayout($record, auth()->id());

                            Notification::make()
                                ->success()
                                ->title('تمت المعاملة بنجاح')
                                ->body('تم الخصم وتسجيل العملية في السجل المالي')
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('فشل التنفيذ')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // 🔴 زر الرفض
                \Filament\Actions\Action::make('reject')
                    ->label('رفض الطلب')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PayoutRequest $record) => $record->status === 'pending')
                    ->form([
                        Textarea::make('reason')
                            ->label('سبب الرفض')
                            ->required()
                    ])
                    ->action(function (PayoutRequest $record, array $data, PayoutService $service) {
                        try {
                            $service->rejectPayout($record, auth()->id(), $data['reason']);

                            Notification::make()
                                ->success()
                                ->title('تم الرفض بنجاح')
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('حدث خطأ')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayoutRequests::route('/'),
        ];
    }
}