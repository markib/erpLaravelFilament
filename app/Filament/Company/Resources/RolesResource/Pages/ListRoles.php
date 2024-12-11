<?php

namespace App\Filament\Company\Resources\RolesResource\Pages;

use App\Filament\Company\Resources\RolesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RolesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
