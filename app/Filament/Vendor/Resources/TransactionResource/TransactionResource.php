<?php

namespace App\Filament\Vendor\Resources\TransactionResource;

use App\Filament\Vendor\Resources\TransactionResource\Pages\CreateTransaction;
use App\Filament\Vendor\Resources\TransactionResource\Pages\EditTransaction;
use App\Filament\Vendor\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Vendor\Resources\TransactionResource\Schemas\TransactionForm;
use App\Filament\Vendor\Resources\TransactionResource\Tables\TransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Transaction';

    public static function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }
}
