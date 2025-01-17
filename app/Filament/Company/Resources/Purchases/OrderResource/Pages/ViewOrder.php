<?php

namespace App\Filament\Company\Resources\Purchases\OrderResource\Pages;

use App\Enums\Accounting\DocumentType;
use App\Filament\Company\Resources\Purchases\OrderResource;
use App\Filament\Company\Resources\SupplierResource;
use App\Filament\Infolists\Components\DocumentPreview;
use App\Models\Accounting\Order;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\MaxWidth;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::SixExtraLarge;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Order::getApproveDraftAction(),
                Order::getMarkAsSentAction(),
                Order::getMarkAsAcceptedAction(),
                Order::getMarkAsDeclinedAction(),
                Order::getReplicateAction(),
                Order::getConvertToBillAction(),
            ])
                ->label('Actions')
                ->button()
                ->outlined()
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-c-chevron-down')
                ->iconSize(IconSize::Small)
                ->iconPosition(IconPosition::After),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Order Details')
                    ->columns(4)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Order #'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('vendor.supplier_name')
                                    ->label('Vendor')
                                    ->color('primary')
                                    ->weight(FontWeight::SemiBold)
                                    ->url(static fn (Order $record) => SupplierResource::getUrl('edit', ['record' => $record->vendor_id])),
                                TextEntry::make('expiration_date')
                                    ->label('Expiration Date')
                                    ->asRelativeDay(),
                                TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->placeholder('Not Approved')
                                    ->date(),
                                TextEntry::make('last_sent_at')
                                    ->label('Last Sent')
                                    ->placeholder('Never')
                                    ->date(),
                                TextEntry::make('accepted_at')
                                    ->label('Accepted At')
                                    ->placeholder('Not Accepted')
                                    ->date(),
                            ])->columnSpan(1),
                        DocumentPreview::make()
                            ->type(DocumentType::Order),
                    ]),
            ]);
    }
}
