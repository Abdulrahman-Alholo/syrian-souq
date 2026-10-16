<?php

namespace App\Filament\Resources\PayoutRequests\Pages;

use App\Filament\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPayoutRequest extends ViewRecord
{
    protected static string $resource = PayoutRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
