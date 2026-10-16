<?php

namespace App\Filament\Vendor\Resources\TransactionResource\Pages;

use App\Filament\Vendor\Resources\TransactionResource\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;
}
