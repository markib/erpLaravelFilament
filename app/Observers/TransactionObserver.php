<?php

namespace App\Observers;

use App\Models\Accounting\Transaction;
use App\Services\TransactionService;

class TransactionObserver
{
    public function __construct(
        protected TransactionService $transactionService,
    ) {}

    /**
     * Handle the Transaction "saving" event.
     */
    public function saving(Transaction $transaction): void
    {
        if ($transaction->type->isTransfer() && $transaction->description === null) {
            $transaction->description = 'Account Transfer';
        }
    }

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        $this->transactionService->createJournalEntries($transaction);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        $transaction->refresh(); // DO NOT REMOVE

        $this->transactionService->updateJournalEntries($transaction);
    }

    /**
     * Handle the Transaction "deleting" event.
     */
    public function deleting(Transaction $transaction): void
    {
        $this->transactionService->deleteJournalEntries($transaction);
    }
}
