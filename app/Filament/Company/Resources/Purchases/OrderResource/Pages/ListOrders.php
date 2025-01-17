<?php

namespace App\Filament\Company\Resources\Purchases\OrderResource\Pages;

use App\Enums\Accounting\OrderStatus;
use App\Filament\Company\Resources\Purchases\OrderResource;
use App\Filament\Company\Resources\Purchases\OrderResource\Widgets\OrderOverview;
use App\Models\Accounting\Order;
use Filament\Actions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OrderOverview::make(),
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

            'active' => Tab::make()
                ->label('Active')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->active();
                })
                ->badge(Order::active()->count()),

            'draft' => Tab::make()
                ->label('Draft')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->where('status', OrderStatus::Draft);
                })
                ->badge(Order::where('status', OrderStatus::Draft)->count()),
        ];
    }
}
