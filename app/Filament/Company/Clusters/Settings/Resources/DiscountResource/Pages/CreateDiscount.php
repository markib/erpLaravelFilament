<?php

namespace App\Filament\Company\Clusters\Settings\Resources\DiscountResource\Pages;

use App\Filament\Company\Clusters\Settings\Resources\DiscountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscount extends CreateRecord
{
    protected static string $resource = DiscountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
