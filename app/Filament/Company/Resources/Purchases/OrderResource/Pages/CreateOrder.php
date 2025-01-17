<?php

namespace App\Filament\Company\Resources\Purchases\OrderResource\Pages;

use App\Concerns\ManagesLineItems;
use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Purchases\OrderResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class CreateOrder extends CreateRecord
{
    use ManagesLineItems;
    use RedirectToListPage;

    protected static string $resource = OrderResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Estimate $record */
        $record = parent::handleRecordCreation($data);

        $this->handleLineItems($record, collect($data['lineItems'] ?? []));

        $totals = $this->updateDocumentTotals($record, $data);

        $record->updateQuietly($totals);

        return $record;
    }
}
