<?php

namespace App\Filament\Company\Resources\Purchases\BillResource\Pages;

use App\Concerns\ManagesLineItems;
use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Purchases\BillResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class CreateBill extends CreateRecord
{
    use ManagesLineItems;
    use RedirectToListPage;

    protected static string $resource = BillResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function handleRecordCreation(array $data): Model
    {

        /** @var Invoice $record */
        $record = parent::handleRecordCreation($data);

        $this->handleLineItems($record, collect($data['lineItems'] ?? []));

        $totals = $this->updateDocumentTotals($record, $data);

        $record->updateQuietly($totals);

        return $record;
    }
}
