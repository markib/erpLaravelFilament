<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Concerns\ManagesLineItems;
use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Accounting\Invoice;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class CreateInvoice extends CreateRecord
{
    use ManagesLineItems;
    use RedirectToListPage;

    protected static string $resource = InvoiceResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function handleRecordCreation(array $data): Model
    {

        // // Ensure salesTaxes are included in each line item
        // $lineItems = collect($data['lineItems'] ?? []);
        // $lineItems->each(function (&$lineItem) { // Pass by reference to modify each item
        //     $lineItem['salesTaxes'] = $lineItem['salesTaxes'] ?? []; // Add default value if not set
        // });

        /** @var Invoice $record */
        $record = parent::handleRecordCreation($data);

        $this->handleLineItems($record, collect($data['lineItems'] ?? []));

        $totals = $this->updateDocumentTotals($record, $data);

        $record->updateQuietly($totals);

        return $record;
    }
}
