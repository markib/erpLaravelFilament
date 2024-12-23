<?php

namespace App\Observers;

use App\Models\Common\Offering;

class OfferingObserver
{
    /**
     * Handle the Offering "created" event.
     */
    public function created(Offering $offering): void
    {
        // Sync the adjustments when the offering is created
        if (isset($offering->sales_tax_ids)) {
            $offering->salesTaxes()->sync($offering->sales_tax_ids);
        }

        // Sync any other adjustments as needed
        if (isset($offering->sales_discount_ids)) {
            $offering->salesDiscounts()->sync($offering->sales_discount_ids);
        }
    }

    public function saving(Offering $offering): void
    {
        $offering->clearSellableAdjustments();
        $offering->clearPurchasableAdjustments();
    }

    /**
     * Handle the Offering "updated" event.
     */
    public function updated(Offering $offering): void
    {
        // Sync the adjustments when the offering is updated
        if (isset($offering->sales_tax_ids)) {
            $offering->salesTaxes()->sync($offering->sales_tax_ids);
        }

        // Sync other adjustments
        if (isset($offering->sales_discount_ids)) {
            $offering->salesDiscounts()->sync($offering->sales_discount_ids);
        }
    }

    /**
     * Handle the Offering "deleted" event.
     */
    public function deleted(Offering $offering): void
    {
        // Optionally, handle the deletion of related adjustments if needed
        $offering->adjustments()->detach();
        $offering->adjustments()->delete();
    }

    /**
     * Handle the Offering "restored" event.
     */
    public function restored(Offering $offering): void
    {
        //
    }

    /**
     * Handle the Offering "force deleted" event.
     */
    public function forceDeleted(Offering $offering): void
    {
        //
    }
}
