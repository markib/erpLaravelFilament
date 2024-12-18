<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Enums\Accounting\InvoiceStatus;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Filament\Company\Resources\Sales\InvoiceResource\Widgets;
use App\Models\Accounting\Invoice;
use Filament\Actions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\InvoiceOverview::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return 'max-w-8xl';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('All'),

            'unpaid' => Tab::make()
                ->label('Unpaid')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->unpaid();
                })
                ->badge(Invoice::unpaid()->count()),

            'draft' => Tab::make()
                ->label('Draft')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->where('status', InvoiceStatus::Draft);
                })
                ->badge(Invoice::where('status', InvoiceStatus::Draft)->count()),
        ];
    }
}
