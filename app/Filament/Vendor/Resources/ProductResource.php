<?php
namespace App\Filament\Vendor\Resources;

use App\Filament\Vendor\Resources\ProductResource\Pages;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Category;

// 1. استدعاءات التخطيط (Filament v4 Schemas)
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

// 2. استدعاءات حقول الإدخال (Filament v4 Forms)
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

use Filament\Resources\Resource;
use Filament\Tables\Table;

use Illuminate\Support\Str;
use Filament\Tables\Actions\ViewAction;
use Filament\Schemas\Components\Actions\Action;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $modelLabel = 'منتج';
    protected static ?string $pluralModelLabel = 'المنتجات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('المعلومات الأساسية')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name_ar')
                        ->label('الاسم (عربي)')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                    TextInput::make('name_en')->label('الاسم (إنجليزي)'),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true),
                    Select::make('category_id')->label('القسم')->options(Category::active()->pluck('name_ar', 'id'))->searchable()->required(),
                ]),
                Textarea::make('description')->label('الوصف')->columnSpanFull()
            ]),

            Section::make('صور المنتج')->schema([
                SpatieMediaLibraryFileUpload::make('thumbnail')->label('الصورة الرئيسية')->collection('product_thumbnail')->image()->required(),
                SpatieMediaLibraryFileUpload::make('gallery')->label('معرض الصور')->collection('product_gallery')->multiple()->image(),
            ])->columns(2),

           Section::make('الأسعار والمخزون')->schema([
                Toggle::make('has_variants')
                    ->label('هذا المنتج يحتوي على خيارات (ألوان/مقاسات)')
                    ->live()
                    ->afterStateHydrated(function (Toggle $component, ?Product $record) {
                        if ($record && $record->variants()->count() > 0) {
                            $component->state(true);
                        }
                    })->default(false),

                TextInput::make('base_price')
                    ->label('السعر الأساسي')
                    ->numeric()
                    ->hidden(fn (Get $get) => $get('has_variants'))
                    ->required(fn (Get $get) => !$get('has_variants'))
                    ->dehydrated(false)
                    ->afterStateHydrated(fn ($component, ?Product $record) => 
                        $component->state($record ? floatval($record->min_price) : null)
                    ),

                Section::make('المولد الذكي للنسخ 🪄')
                    ->description('اختر كل الخصائص (مثلاً: أحمر، أزرق، M، L) وسنقوم بدمجها وتوليد السطور تلقائياً (بدون تكرار)!')
                    ->hidden(fn (Get $get) => ! $get('has_variants'))
                    ->schema([
                        Select::make('magic_values')
                            ->label('1. اختر القيم التي تريد دمجها')
                            ->multiple()
                            ->options(function () {
                                return \App\Modules\Catalog\Models\AttributeValue::with('attribute')
                                    ->get()
                                    ->groupBy('attribute.name_ar')
                                    ->map(fn ($values) => $values->pluck('value_ar', 'id'))
                                    ->toArray();
                            })
                            ->live(),

                        Toggle::make('magic_generate')
                            ->label('2. 🚀 اضغط هنا لتوليد النسخ بالأسفل!')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) return; 

                                $selectedValues = $get('magic_values') ?? [];
                                if (!empty($selectedValues)) {
                                    $values = \App\Modules\Catalog\Models\AttributeValue::whereIn('id', $selectedValues)->get();
                                    $grouped = $values->groupBy('attribute_id')->map(fn($group) => $group->pluck('id')->toArray())->toArray();

                                    // خوارزمية الدمج
                                    $combinations = [[]];
                                    foreach ($grouped as $propertyValues) {
                                        $tmp = [];
                                        foreach ($combinations as $combination) {
                                            foreach ($propertyValues as $valueId) {
                                                $tmp[] = array_merge($combination, [$valueId]);
                                            }
                                        }
                                        $combinations = $tmp;
                                    }

                                    $existingVariants = $get('variants') ?? [];

                                    // 🛡️ حماية ChatGPT: جلب التراكيب الموجودة مسبقاً لمنع التكرار
                                    $existingCombos = collect($existingVariants)
                                        ->map(fn ($variant) => collect($variant['attributeValues'] ?? [])->sort()->values()->toArray())
                                        ->toArray();

                                    foreach ($combinations as $combo) {
                                        // ترتيب القيم لفحصها (مثلاً: أحمر+M هي نفسها M+أحمر)
                                        $normalizedCombo = collect($combo)->sort()->values()->toArray();

                                        // فحص التكرار
                                        $exists = collect($existingCombos)->contains(function ($existing) use ($normalizedCombo) {
                                            return $existing === $normalizedCombo;
                                        });

                                        // إذا لم تكن موجودة، قم بإضافتها باستخدام UUID للحفاظ على استقرار الواجهة
                                        if (!$exists) {
                                            $uuid = (string) \Illuminate\Support\Str::uuid();
                                            $existingVariants[$uuid] = [
                                                'price' => $get('base_price') ?? 0,
                                                'stock' => 0,
                                                'attributeValues' => $combo, 
                                            ];
                                            $existingCombos[] = $normalizedCombo; // إضافتها للقائمة المحمية لمنع تكرارها فوراً
                                        }
                                    }

                                    $set('variants', $existingVariants); 
                                }
                                
                                $set('magic_values', null);
                                $set('magic_generate', false); 
                            }),
                    ])->columns(2),

                Repeater::make('variants')
                    ->relationship('variants')
                    ->label('النسخ المتوفرة (كل سطر يمثل قطعة فعلية)')
                    ->default([]) // 🛡️ لمسة الـ Production
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('price')->label('السعر')->numeric()->required(),
                            TextInput::make('stock')->label('المخزون')->numeric()->default(0),
                        ]),
                        
                        // 🛡️ حماية ChatGPT: إضافة relationship لضمان حفظ البيانات في قاعدة البيانات
                        // 🛡️ حقل الخصائص المحمي بالدرع الواقي
                        Select::make('attributeValues')
                            ->relationship(name: 'attributeValues', titleAttribute: 'value_ar')
                            ->options(function () {
                                return \App\Modules\Catalog\Models\AttributeValue::with('attribute')
                                    ->get()
                                    ->groupBy('attribute.name_ar')
                                    ->map(fn ($values) => $values->pluck('value_ar', 'id'))
                                    ->toArray();
                            })
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->label('تركيبة هذه النسخة (الخصائص)')
                            ->helperText('تأكد أن السعر والمخزون صحيحان.')
                            ->columnSpanFull()
                            // 🔥🔥🔥 درع الحماية لمنع اختيار قيمتين من نفس الخاصية 🔥🔥🔥
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if (empty($value)) return;

                                    // جلب القيم المختارة فقط من قاعدة البيانات لتفحصها
                                    $selectedAttributesIds = \App\Modules\Catalog\Models\AttributeValue::whereIn('id', $value)
                                        ->pluck('attribute_id')
                                        ->toArray();

                                    // إذا كان عدد الخصائص الفريدة (بعد إزالة التكرار) أقل من عدد القيم المختارة
                                    // فهذا يعني حتماً أن التاجر اختار قيمتين أو أكثر لنفس الخاصية الأب (مثلاً لونين)
                                    if (count(array_unique($selectedAttributesIds)) !== count($selectedAttributesIds)) {
                                        $fail('❌ خطأ: لا يمكن اختيار أكثر من قيمة واحدة لنفس النوع (مثلاً: لا تختار لونين في نفس السطر).');
                                    }
                                };
                            }),
                    ])
                    ->hidden(fn (Get $get, ?Product $record) =>
                        ! $get('has_variants') && ($record ? $record->variants()->count() === 0 : true)
                    )->addActionLabel('إضافة نسخة يدوياً'),
            ]),
            Section::make('إعدادات المنتج')->schema([
                Toggle::make('is_active')->label('المنتج مفعل')->default(true),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            \Filament\Tables\Columns\SpatieMediaLibraryImageColumn::make('thumbnail')->collection('product_thumbnail')->label('الصورة')->circular(),
            \Filament\Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable(),
            \Filament\Tables\Columns\TextColumn::make('category.name_ar')->label('القسم'),
            \Filament\Tables\Columns\TextColumn::make('price_range')
                ->label('السعر')
                ->getStateUsing(fn (Product $record) => 
                    floatval($record->min_price) == floatval($record->max_price) 
                        ? floatval($record->min_price) 
                        : floatval($record->min_price) . ' - ' . floatval($record->max_price)
                ),
            \Filament\Tables\Columns\ToggleColumn::make('is_active')->label('مفعل'),
        ])
        // 👈 السحر هنا: جعلنا السطر بالكامل يوجهك لصفحة التعديل!
        ->recordUrl(fn (Product $record): string => static::getUrl('edit', ['record' => $record]))        // تركنا الأزرار فارغة لكي نتخطى الكلاس المفقود
        ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}