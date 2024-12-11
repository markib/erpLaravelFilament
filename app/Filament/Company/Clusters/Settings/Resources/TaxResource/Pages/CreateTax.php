<?php

namespace App\Filament\Company\Clusters\Settings\Resources\TaxResource\Pages;

use App\Filament\Company\Clusters\Settings\Resources\TaxResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTax extends CreateRecord
{
    protected static string $resource = TaxResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
