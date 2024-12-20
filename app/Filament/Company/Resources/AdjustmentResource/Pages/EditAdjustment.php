<?php

namespace App\Filament\Company\Resources\AdjustmentResource\Pages;

use App\Filament\Company\Resources\AdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdjustment extends EditRecord
{
    protected static string $resource = AdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
