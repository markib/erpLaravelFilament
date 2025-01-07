<?php

namespace App\Observers;

use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Transaction;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\DB;

class InvoiceObserver
{
    protected $stockMovementService;

    public function __construct(StockMovementService $stockMovementService)
    {
        $this->stockMovementService = $stockMovementService;
    }

    public function saving(Invoice $invoice): void
    {
        if ($invoice->approved_at && $invoice->is_currently_overdue) {
            $invoice->status = InvoiceStatus::Overdue;
        }
    }

    public function saved(Invoice $invoice): void
    {
        if ($invoice->wasChanged('approved_at') && $invoice->approved_at) {
            $this->stockMovementService->updateStockFromInvoice($invoice);
        }
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->lineItems()->each(function (DocumentLineItem $lineItem) {
                $lineItem->delete();
            });

            $invoice->transactions()->each(function (Transaction $transaction) {
                $transaction->delete();
            });
        });
    }
}
