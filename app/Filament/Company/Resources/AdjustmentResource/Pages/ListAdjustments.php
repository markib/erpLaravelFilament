<?php

namespace App\Filament\Company\Resources\AdjustmentResource\Pages;

use App\Filament\Company\Resources\AdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Filters\SelectFilter;

class ListAdjustments extends ListRecords
{
    protected static string $resource = AdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('status')
            ->options([
                'pending' => 'Pending',
                'approved' => 'Approved',
                'reversed' => 'Reversed',
            ])
                ->default('pending')
                ->label('Status'),
            SelectFilter::make('category')
            ->options([
                'tax' => 'Tax',
                'discount' => 'Discount',
                'adjustment' => 'Adjustment',
            ])
                ->label('Category'),
            SelectFilter::make('type')
            ->options([
                'sales' => 'Sales',
                'purchase' => 'Purchase',
            ])
                ->label('Type'),
        ];
    }
}
