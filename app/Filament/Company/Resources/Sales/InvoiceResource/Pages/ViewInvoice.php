<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Filament\Company\Resources\CustomerResource;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Accounting\Invoice;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Invoice::getApproveDraftAction(),
                Invoice::getMarkAsSentAction(),
                Invoice::getReplicateAction(),
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
                Section::make('Invoice Details')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Invoice #'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('client.customer_name')
                            ->label('Client')
                            ->color('primary')
                            ->weight(FontWeight::SemiBold)
                            ->url(static fn (Invoice $record) => CustomerResource::getUrl('edit', ['record' => $record->client_id])),
                        TextEntry::make('total')
                            ->label('Total')
                            ->money(),
                        TextEntry::make('amount_due')
                            ->label('Amount Due')
                        //->money(),
                            ->currency(static fn (Invoice $record) => $record->currency_code),
                        TextEntry::make('date')
                            ->label('Date')
                            ->date(),
                        TextEntry::make('due_date')
                            ->label('Due')
                            ->formatStateUsing(fn (string $state): string => Carbon::parse($state)->diffForHumans()),
                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->placeholder('Not Approved')
                            ->date(),
                        TextEntry::make('last_sent')
                            ->label('Last Sent')
                            ->placeholder('Never')
                            ->date(),
                        TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->placeholder('Not Paid')
                            ->date(),

                    ])->columnSpan(1),
                Grid::make()
                    ->schema([
                        ViewEntry::make('invoice-view')
                            ->label('View Invoice')
                            ->columnSpan(3)
                            ->view('filament.company.resources.sales.invoices.components.invoice-view')
                            ->viewData([
                                'invoice' => $this->record,
                            ]),
                    ])
                    ->columnSpan(3),
            ]);
    }
}
