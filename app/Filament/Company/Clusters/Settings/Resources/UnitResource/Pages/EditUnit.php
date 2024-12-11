<?php

namespace App\Filament\Company\Clusters\Settings\Resources\UnitResource\Pages;

use App\Filament\Company\Clusters\Settings\Resources\UnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUnit extends EditRecord
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
