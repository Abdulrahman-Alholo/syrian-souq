<?php

namespace App\Filament\Vendor\Resources\TransactionResource\Pages;
use App\Filament\Vendor\Resources\TransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        // 🔴 مصفوفة فارغة = إخفاء زر الإضافة "New"
        return [];
    }
}