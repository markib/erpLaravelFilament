<?php

namespace App\Observers;

use App\Models\Product\Product;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {

        // Sync the adjustments when the Product is created
        if (isset($product->sales_tax_ids)) {
            $product->salesTaxes()->sync($product->sales_tax_ids);
        }

        // Sync any other adjustments as needed
        if (isset($product->sales_discount_ids)) {
            $product->salesDiscounts()->sync($product->sales_discount_ids);
        }
    }

    public function saving(Product $product): void
    {

        $product->clearSellableAdjustments();
        $product->clearPurchasableAdjustments();
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        // Sync the adjustments when the Product is updated
        if (isset($product->sales_tax_ids)) {
            $product->salesTaxes()->sync($product->sales_tax_ids);
        }

        // Sync other adjustments
        if (isset($Product->sales_discount_ids)) {
            $Product->salesDiscounts()->sync($Product->sales_discount_ids);
        }
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        // Optionally, handle the deletion of related adjustments if needed
        $product->adjustments()->detach();
        $product->adjustments()->delete();
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        //
    }
}
