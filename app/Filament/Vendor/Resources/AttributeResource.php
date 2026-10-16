<?php
// 1. مسار الملف الصحيح لأنه موجود مباشرة داخل مجلد Resources
namespace App\Filament\Vendor\Resources;

// 2. مسار الصفحات الموجودة داخل مجلد Attributes
use App\Filament\Vendor\Resources\Attributes\Pages;
use App\Modules\Catalog\Models\Attribute;

// 3. استدعاءات Filament v4 الصحيحة
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch'; 
    protected static ?string $modelLabel = 'خاصية (Attribute)';
    protected static ?string $pluralModelLabel = 'الخصائص';
    protected static string|\UnitEnum|null $navigationGroup = 'إعدادات المتجر';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الخاصية الأساسية')->schema([
                TextInput::make('name_ar')->label('الاسم (عربي)')->required(),
                TextInput::make('name_en')->label('الاسم (إنجليزي)'),
            ])->columns(2),

            Section::make('قيم الخاصية')->schema([
                Repeater::make('values')
                    ->relationship('values')
                    ->label('القيم (مثل: أحمر، أزرق / M, L)')
                    ->schema([
                        TextInput::make('value_ar')->label('القيمة (عربي)')->required(),
                        TextInput::make('value_en')->label('القيمة (إنجليزي)'),
                    ])->columns(2)->addActionLabel('إضافة قيمة جديدة')->reorderable(false)
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name_ar')->label('الاسم (عربي)')->searchable(),
            TextColumn::make('name_en')->label('الاسم (إنجليزي)')->searchable(),
            TextColumn::make('values_count')->counts('values')->label('عدد القيم')->badge()->color('success'),
        ])
        // 👈 السحر هنا: السطر بالكامل يوجهك لصفحة التعديل
        ->recordUrl(fn (Attribute $record): string => static::getUrl('edit', ['record' => $record]))
        // 👈 إفراغ الأزرار لتجنب الشاشة الحمراء
        ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttributes::route('/'),
            'create' => Pages\CreateAttribute::route('/create'),
            'edit' => Pages\EditAttribute::route('/{record}/edit'),
        ];
    }
}