<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Casts\RateCast;
use App\Collections\Accounting\InvoiceCollection;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\TransactionType;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Banking\BankAccount;
use App\Models\Parties\Customer;
use App\Observers\InvoiceObserver;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Database\Factories\Accounting\InvoiceFactory;
use Filament\Actions\Action;
use Filament\Actions\MountableAction;
use Filament\Actions\ReplicateAction;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

#[ObservedBy(InvoiceObserver::class)]
#[CollectedBy(InvoiceCollection::class)]
class Invoice extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'company_id',
        'client_id',
        'logo',
        'header',
        'subheader',
        'invoice_number',
        'order_number',
        'date',
        'due_date',
        'approved_at',
        'paid_at',
        'last_sent',
        'status',
        'currency_code',
        'discount_method',
        'discount_computation',
        'discount_rate',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'amount_paid',
        'terms',
        'footer',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_sent' => 'datetime',
        'status' => InvoiceStatus::class,
        'discount_method' => DocumentDiscountMethod::class,
        'discount_computation' => AdjustmentComputation::class,
        'discount_rate' => RateCast::class,
        'subtotal' => MoneyCast::class,
        'tax_total' => MoneyCast::class,
        'discount_total' => MoneyCast::class,
        'total' => MoneyCast::class,
        'amount_paid' => MoneyCast::class,
        'amount_due' => MoneyCast::class,
    ];

    // public function newCollection(array $models = []): InvoiceCollection
    // {
    //     return new InvoiceCollection($models);
    // }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems(): MorphMany
    {
        return $this->morphMany(DocumentLineItem::class, 'documentable');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function payments(): MorphMany
    {
        return $this->transactions()->where('is_payment', true);
    }

    public function deposits(): MorphMany
    {
        return $this->transactions()->where('type', TransactionType::Deposit)->where('is_payment', true);
    }

    public function withdrawals(): MorphMany
    {
        return $this->transactions()->where('type', TransactionType::Withdrawal)->where('is_payment', true);
    }

    public function approvalTransaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'transactionable')
            ->where('type', TransactionType::Journal);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            InvoiceStatus::Paid,
            InvoiceStatus::Void,
            InvoiceStatus::Draft,
            InvoiceStatus::Overpaid,
        ]);
    }

    protected function isCurrentlyOverdue(): Attribute
    {
        return Attribute::get(function () {
            return $this->due_date->isBefore(today()) && $this->canBeOverdue();
        });
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function canRecordPayment(): bool
    {
        return ! in_array($this->status, [
            InvoiceStatus::Draft,
            InvoiceStatus::Paid,
            InvoiceStatus::Void,
        ]);
    }

    public function canBulkRecordPayment(): bool
    {
        return ! in_array($this->status, [
            InvoiceStatus::Draft,
            InvoiceStatus::Paid,
            InvoiceStatus::Void,
            InvoiceStatus::Overpaid,
        ]);
    }

    public function canBeOverdue(): bool
    {
        return in_array($this->status, InvoiceStatus::canBeOverdue());
    }

    public function hasPayments(): bool
    {
        return $this->payments->isNotEmpty();
    }

    public static function getNextDocumentNumber(): string
    {
        $company = auth()->user()->currentCompany;

        if (! $company) {
            throw new \RuntimeException('No current company is set for the user.');
        }

        $defaultInvoiceSettings = $company->defaultInvoice;

        $numberPrefix = $defaultInvoiceSettings->number_prefix;
        $numberDigits = $defaultInvoiceSettings->number_digits;

        $latestDocument = static::query()
            ->whereNotNull('invoice_number')
            ->latest('invoice_number')
            ->first();

        $lastNumberNumericPart = $latestDocument
            ? (int) substr($latestDocument->invoice_number, strlen($numberPrefix))
            : 0;

        $numberNext = $lastNumberNumericPart + 1;

        return $defaultInvoiceSettings->getNumberNext(
            padded: true,
            format: true,
            prefix: $numberPrefix,
            digits: $numberDigits,
            next: $numberNext
        );
    }

    public function recordPayment(array $data): void
    {
        $isRefund = $this->status === InvoiceStatus::Overpaid;

        if ($isRefund) {
            $transactionType = TransactionType::Withdrawal;
            $transactionDescription = "Invoice #{$this->invoice_number}: Refund to {$this->client->customer_name}";
        } else {
            $transactionType = TransactionType::Deposit;
            $transactionDescription = "Invoice #{$this->invoice_number}: Payment from {$this->client->customer_name}";
        }

        $bankAccount = BankAccount::findOrFail($data['bank_account_id']);
        $bankAccountCurrency = $bankAccount->account->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        $invoiceCurrency = $this->currency_code;
        $requiresConversion = $invoiceCurrency !== $bankAccountCurrency;

        if ($requiresConversion) {
            $amountInInvoiceCurrencyCents = CurrencyConverter::convertToCents($data['amount'], $invoiceCurrency);
            $amountInBankCurrencyCents = CurrencyConverter::convertBalance(
                $amountInInvoiceCurrencyCents,
                $invoiceCurrency,
                $bankAccountCurrency
            );
            $formattedAmountForBankCurrency = CurrencyConverter::convertCentsToFormatSimple(
                $amountInBankCurrencyCents,
                $bankAccountCurrency
            );
        } else {
            $formattedAmountForBankCurrency = $data['amount']; // Already in simple format
        }

        // Create transaction
        $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => $transactionType,
            'is_payment' => true,
            'posted_at' => $data['posted_at'],
            'amount' => $formattedAmountForBankCurrency,
            'payment_method' => $data['payment_method'],
            'bank_account_id' => $data['bank_account_id'],
            'account_id' => Account::getAccountsReceivableAccount()->id,
            'description' => $transactionDescription,
            'notes' => $data['notes'] ?? null,
        ]);

    }

    public function approveDraft(?Carbon $approvedAt = null): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('Invoice is not in draft status.');
        }

        $this->createApprovalTransaction();

        $approvedAt ??= now();

        $this->update([
            'approved_at' => $approvedAt,
            'status' => InvoiceStatus::Unsent,
        ]);
    }

    public function createApprovalTransaction(): void
    {
        $total = $this->formatAmountToDefaultCurrency($this->getRawOriginal('total'));

        $transaction = $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => TransactionType::Journal,
            'posted_at' => $this->date,
            'amount' => $total,
            'description' => 'Invoice Approval for Invoice #' . $this->invoice_number,
        ]);

        $baseDescription = "{$this->client->customer_name}: Invoice #{$this->invoice_number}";

        $transaction->journalEntries()->create([
            'company_id' => $this->company_id,
            'type' => JournalEntryType::Debit,
            'account_id' => Account::getAccountsReceivableAccount()->id,
            'amount' => $total,
            'description' => $baseDescription,
        ]);

        $totalLineItemSubtotalCents = $this->convertAmountToDefaultCurrency((int) $this->lineItems()->sum('subtotal'));
        $invoiceDiscountTotalCents = $this->convertAmountToDefaultCurrency((int) $this->getRawOriginal('discount_total'));
        $remainingDiscountCents = $invoiceDiscountTotalCents;

        foreach ($this->lineItems as $index => $lineItem) {
            $lineItemDescription = "{$baseDescription} › {$lineItem->offering->name}";

            $lineItemSubtotal = $this->formatAmountToDefaultCurrency($lineItem->getRawOriginal('subtotal'));

            $transaction->journalEntries()->create([
                'company_id' => $this->company_id,
                'type' => JournalEntryType::Credit,
                'account_id' => $lineItem->offering->income_account_id,
                'amount' => $lineItemSubtotal,
                'description' => $lineItemDescription,
            ]);

            foreach ($lineItem->adjustments as $adjustment) {
                $adjustmentAmount = $this->formatAmountToDefaultCurrency($lineItem->calculateAdjustmentTotalAmount($adjustment));

                $transaction->journalEntries()->create([
                    'company_id' => $this->company_id,
                    'type' => $adjustment->category->isDiscount() ? JournalEntryType::Debit : JournalEntryType::Credit,
                    'account_id' => $adjustment->account_id,
                    'amount' => $adjustmentAmount,
                    'description' => $lineItemDescription,
                ]);
            }

            if ($this->discount_method->isPerDocument() && $totalLineItemSubtotalCents > 0) {
                $lineItemSubtotalCents = $this->convertAmountToDefaultCurrency((int) $lineItem->getRawOriginal('subtotal'));

                if ($index === $this->lineItems->count() - 1) {
                    $lineItemDiscount = $remainingDiscountCents;
                } else {
                    $lineItemDiscount = (int) round(
                        ($lineItemSubtotalCents / $totalLineItemSubtotalCents) * $invoiceDiscountTotalCents
                    );
                    $remainingDiscountCents -= $lineItemDiscount;
                }

                if ($lineItemDiscount > 0) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => JournalEntryType::Debit,
                        'account_id' => Account::getSalesDiscountAccount()->id,
                        'amount' => CurrencyConverter::convertCentsToFormatSimple($lineItemDiscount),
                        'description' => "{$lineItemDescription} (Proportional Discount)",
                    ]);
                }
            }
        }
    }

    public function updateApprovalTransaction(): void
    {
        $transaction = $this->approvalTransaction;

        if ($transaction) {
            $transaction->delete();
        }

        $this->createApprovalTransaction();
    }

    public function convertAmountToDefaultCurrency(int $amountCents): int
    {
        $defaultCurrency = CurrencyAccessor::getDefaultCurrency();
        $needsConversion = $this->currency_code !== $defaultCurrency;

        if ($needsConversion) {
            return CurrencyConverter::convertBalance($amountCents, $this->currency_code, $defaultCurrency);
        }

        return $amountCents;
    }

    public function formatAmountToDefaultCurrency(int $amountCents): string
    {
        $convertedCents = $this->convertAmountToDefaultCurrency($amountCents);

        return CurrencyConverter::convertCentsToFormatSimple($convertedCents);
    }

    public static function getApproveDraftAction(string $action = Action::class): MountableAction
    {
        return $action::make('approveDraft')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->visible(function (self $record) {
                return $record->isDraft();
            })
            ->databaseTransaction()
            ->successNotificationTitle('Invoice Approved')
            ->action(function (self $record, MountableAction $action) {
                $record->approveDraft();

                $action->success();
            });
    }

    public static function getMarkAsSentAction(string $action = Action::class): MountableAction
    {
        return $action::make('markAsSent')
            ->label('Mark as Sent')
            ->icon('heroicon-o-paper-airplane')
            ->visible(static function (self $record) {
                return ! $record->last_sent;
            })
            ->successNotificationTitle('Invoice Sent')
            ->action(function (self $record, MountableAction $action) {
                $record->update([
                    'status' => InvoiceStatus::Sent,
                    'last_sent' => now(),
                ]);

                $action->success();
            });
    }

    public static function getReplicateAction(string $action = ReplicateAction::class): MountableAction
    {
        return $action::make()
            ->excludeAttributes([
                'status',
                'amount_paid',
                'amount_due',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
                'invoice_number',
                'date',
                'due_date',
                'approved_at',
                'paid_at',
                'last_sent',
            ])
            ->modal(false)
            ->beforeReplicaSaved(function (self $original, self $replica) {
                $replica->status = InvoiceStatus::Draft;
                $replica->invoice_number = self::getNextDocumentNumber();
                $replica->date = now();
                $replica->due_date = now()->addDays($original->company->defaultInvoice->payment_terms->getDays());
            })
            ->databaseTransaction()
            ->after(function (self $original, self $replica) {
                $original->lineItems->each(function (DocumentLineItem $lineItem) use ($replica) {
                    $replicaLineItem = $lineItem->replicate([
                        'documentable_id',
                        'documentable_type',
                        'subtotal',
                        'total',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]);

                    $replicaLineItem->documentable_id = $replica->id;
                    $replicaLineItem->documentable_type = $replica->getMorphClass();

                    $replicaLineItem->save();

                    $replicaLineItem->adjustments()->sync($lineItem->adjustments->pluck('id'));
                });
            })
            ->successRedirectUrl(static function (self $replica) {
                return InvoiceResource::getUrl('edit', ['record' => $replica]);
            });
    }

    protected static function newFactory(): Factory
    {
        return InvoiceFactory::new();
    }
}
