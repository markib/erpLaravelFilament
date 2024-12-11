<?php

namespace App\Filament\Company\Resources\Purchases\BillResource\Pages;

use App\Filament\Company\Resources\Purchases\BillResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBill extends EditRecord
{
    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
