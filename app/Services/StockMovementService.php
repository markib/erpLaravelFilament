<?php

namespace App\Services;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\StockMovement;
use App\Models\Product\Product;
use Exception;
use Illuminate\Support\Facades\DB;

class StockMovementService
{
    public function createStockMovement(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $stockMovement = StockMovement::create([
                'company_id' => $data['company_id'],
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
                'type' => $data['type'],
                'bill_id' => $data['bill_id'] ?? null,
                'invoice_id' => $data['invoice_id'] ?? null,
            ]);

            $this->updateProductStock($data['product_id'], $data['quantity'], $data['type']);

            return $stockMovement;
        });
    }

    public function updateStockFromInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            foreach ($invoice->lineItems as $item) {
                if ($item->product) {
                    $this->createStockMovement([
                        'company_id' => $invoice->company_id,
                        'product_id' => $item->product->id,
                        'quantity' => $item->quantity,
                        'type' => 'subtraction',
                        'invoice_id' => $invoice->id,
                    ]);
                }
            }
        });
    }

    public function updateStockFromBill(Bill $bill): void
    {
        DB::transaction(function () use ($bill) {
            foreach ($bill->lineItems as $item) {
                if ($item->product) {
                    $this->createStockMovement([
                        'company_id' => $bill->company_id,
                        'product_id' => $item->product->id,
                        'quantity' => $item->quantity,
                        'type' => 'addition',
                        'bill_id' => $bill->id,
                    ]);
                }
            }
        });
    }

    protected function updateProductStock(int $productId, int $quantity, string $type): void
    {
        $product = Product::findOrFail($productId);

        if ($type === 'addition') {
            $product->increment('stock', $quantity);
        } elseif ($type === 'subtraction') {
            if ($product->product_quantity < $quantity) {
                throw new Exception("Insufficient stock for product ID: {$productId}");
            }
            $product->decrement('product_quantity', $quantity);
        } else {
            throw new Exception("Invalid stock movement type: {$type}");
        }
    }
}
