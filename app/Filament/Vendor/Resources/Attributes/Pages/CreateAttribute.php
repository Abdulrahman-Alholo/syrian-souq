<?php

namespace App\Filament\Vendor\Resources\Attributes\Pages;

use App\Filament\Vendor\Resources\AttributeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;

    // 👈 السحر هنا: توجيه المستخدم لصفحة الجدول بعد الإضافة
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}