<?php

namespace App\Filament\Company\Resources\ProductResource\Pages;

use App\Filament\Company\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getView(): string
    {
        return 'filament.company.components.tables.actions.custom-table-header';
    }

    
}
