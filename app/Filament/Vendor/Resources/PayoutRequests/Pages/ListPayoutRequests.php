<?php

namespace App\Filament\Vendor\Resources\PayoutRequests\Pages;

use App\Filament\Vendor\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayoutRequests extends ListRecords
{
    protected static string $resource = PayoutRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
