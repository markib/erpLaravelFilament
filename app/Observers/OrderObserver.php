<?php

namespace App\Observers;

use App\Enums\Accounting\OrderStatus;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Order;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    public function saving(Order $order): void
    {
        if ($order->approved_at && $order->is_currently_expired) {
            $order->status = OrderStatus::Expired;
        }
    }

    public function deleted(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->lineItems()->each(function (DocumentLineItem $lineItem) {
                $lineItem->delete();
            });
        });
    }
}
