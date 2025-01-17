<?php

namespace App\Observers;

use App\Enums\Accounting\BillStatus;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Transaction;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\DB;

class BillObserver
{
    protected $stockMovementService;

    public function __construct(StockMovementService $stockMovementService)
    {
        $this->stockMovementService = $stockMovementService;
    }

    public function created(Bill $bill): void
    {
        // $bill->createInitialTransaction();
    }

    public function saving(Bill $bill): void
    {
        if ($bill->is_currently_overdue) {
            $bill->status = BillStatus::Overdue;
        }
    }

    public function saved(Bill $bill): void
    {
        if ($bill->wasChanged('goods_received_at') && $bill->goods_received_at) {
            $this->stockMovementService->updateStockFromBill($bill);
        }
    }

    /**
     * Handle the Bill "deleted" event.
     */
    public function deleted(Bill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $bill->lineItems()->each(function (DocumentLineItem $lineItem) {
                $lineItem->delete();
            });

            $bill->transactions()->each(function (Transaction $transaction) {
                $transaction->delete();
            });
        });
    }
}
