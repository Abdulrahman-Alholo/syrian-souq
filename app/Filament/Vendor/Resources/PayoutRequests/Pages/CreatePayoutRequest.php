<?php

namespace App\Filament\Vendor\Resources\PayoutRequests\Pages;

use App\Filament\Vendor\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreatePayoutRequest extends CreateRecord
{
    protected static string $resource = PayoutRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $available = PayoutRequestResource::getAvailableBalance();

        if ((float) $data['amount'] > $available) {
            throw ValidationException::withMessages([
                'data.amount' => "المبلغ المطلوب يتجاوز رصيدك القابل للسحب ({$available} SYP).",
            ]);
        }

        $data['vendor_id'] = auth()->user()->vendor->id;
        $data['status'] = 'pending';
        
        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}