<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Casts\RateCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\OrderStatus;
use App\Enums\Accounting\TransactionType;
use App\Filament\Company\Resources\Purchases\BillResource;
use App\Models\Banking\BankAccount;
use App\Models\Locale\Currency;
use App\Models\Parties\Supplier;
use App\Observers\BillObserver;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Filament\Actions\MountableAction;
use Filament\Actions\ReplicateAction;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

#[ObservedBy(BillObserver::class)]
class Bill extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'bills';

    protected $fillable = [
        'company_id',
        'vendor_id',
        'bill_number',
        'order_number',
        'date',
        'due_date',
        'paid_at',
        'status',
        'currency_code',
        'discount_method',
        'discount_computation',
        'discount_rate',
        'subtotal',
        'tax_total',
        'item_type',
        'order_id',
        'discount_total',
        'total',
        'amount_paid',
        'notes',
        'goods_received_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'status' => BillStatus::class,
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function initialTransaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'transactionable')
            ->where('type', TransactionType::Journal);
    }

    protected function isCurrentlyOverdue(): Attribute
    {
        return Attribute::get(function () {
            return $this->due_date->isBefore(today()) && $this->canBeOverdue();
        });
    }

    public function canBeOverdue(): bool
    {
        return in_array($this->status, BillStatus::canBeOverdue());
    }

    public function canRecordPayment(): bool
    {
        return ! in_array($this->status, [
            BillStatus::Paid,
            BillStatus::Void,
        ]);
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

        $defaultBillSettings = $company->defaultBill;

        $numberPrefix = $defaultBillSettings->number_prefix;
        $numberDigits = $defaultBillSettings->number_digits;

        $latestDocument = static::query()
            ->whereNotNull('bill_number')
            ->latest('bill_number')
            ->first();

        $lastNumberNumericPart = $latestDocument
            ? (int) substr($latestDocument->bill_number, strlen($numberPrefix))
            : 0;

        $numberNext = $lastNumberNumericPart + 1;

        return $defaultBillSettings->getNumberNext(
            padded: true,
            format: true,
            prefix: $numberPrefix,
            digits: $numberDigits,
            next: $numberNext
        );
    }

    public function hasInitialTransaction(): bool
    {
        return $this->initialTransaction()->exists();
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BillStatus::Unpaid,
            BillStatus::Partial,
            BillStatus::Overdue,
        ]);
    }

    public function recordPayment(array $data): void
    {
        $transactionType = TransactionType::Withdrawal;
        $transactionDescription = "Bill #{$this->bill_number}: Payment to {$this->vendor->supplier_name}";

        // Add multi-currency handling
        $bankAccount = BankAccount::findOrFail($data['bank_account_id']);
        $bankAccountCurrency = $bankAccount->account->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        $billCurrency = $this->currency_code;
        $requiresConversion = $billCurrency !== $bankAccountCurrency;

        if ($requiresConversion) {
            $amountInBillCurrencyCents = CurrencyConverter::convertToCents($data['amount'], $billCurrency);
            $amountInBankCurrencyCents = CurrencyConverter::convertBalance(
                $amountInBillCurrencyCents,
                $billCurrency,
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
            'account_id' => Account::getAccountsPayableAccount()->id,
            'description' => $transactionDescription,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function createInitialTransaction(?Carbon $postedAt = null): void
    {
        $postedAt ??= $this->date;

        $total = $this->formatAmountToDefaultCurrency($this->getRawOriginal('total'));

        $transaction = $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => TransactionType::Journal,
            'posted_at' => $postedAt,
            'amount' => $total,
            'description' => 'Bill Creation for Bill #' . $this->bill_number,
        ]);

        $baseDescription = "{$this->vendor->supplier_name}: Bill #{$this->bill_number}";

        $transaction->journalEntries()->create([
            'company_id' => $this->company_id,
            'type' => JournalEntryType::Credit,
            'account_id' => Account::getAccountsPayableAccount()->id,
            'amount' => $total,
            'description' => $baseDescription,
        ]);

        $totalLineItemSubtotalCents = $this->convertAmountToDefaultCurrency((int) $this->lineItems()->sum('subtotal'));
        $billDiscountTotalCents = $this->convertAmountToDefaultCurrency((int) $this->getRawOriginal('discount_total'));
        $remainingDiscountCents = $billDiscountTotalCents;

        foreach ($this->lineItems as $index => $lineItem) {
            $lineItemDescription = "{$baseDescription} › {$lineItem->offering->name}";

            $lineItemSubtotal = $this->formatAmountToDefaultCurrency($lineItem->getRawOriginal('subtotal'));

            $transaction->journalEntries()->create([
                'company_id' => $this->company_id,
                'type' => JournalEntryType::Debit,
                'account_id' => $lineItem->offering->expense_account_id,
                'amount' => $lineItemSubtotal,
                'description' => $lineItemDescription,
            ]);

            foreach ($lineItem->adjustments as $adjustment) {
                $adjustmentAmount = $this->formatAmountToDefaultCurrency($lineItem->calculateAdjustmentTotalAmount($adjustment));

                if ($adjustment->isNonRecoverablePurchaseTax()) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => JournalEntryType::Debit,
                        'account_id' => $lineItem->offering->expense_account_id,
                        'amount' => $adjustmentAmount,
                        'description' => "{$lineItemDescription} ({$adjustment->name})",
                    ]);
                } elseif ($adjustment->account_id) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => $adjustment->category->isDiscount() ? JournalEntryType::Credit : JournalEntryType::Debit,
                        'account_id' => $adjustment->account_id,
                        'amount' => $adjustmentAmount,
                        'description' => $lineItemDescription,
                    ]);
                }
            }

            if ($this->discount_method->isPerDocument() && $totalLineItemSubtotalCents > 0) {
                $lineItemSubtotalCents = $this->convertAmountToDefaultCurrency((int) $lineItem->getRawOriginal('subtotal'));

                if ($index === $this->lineItems->count() - 1) {
                    $lineItemDiscount = $remainingDiscountCents;
                } else {
                    $lineItemDiscount = (int) round(
                        ($lineItemSubtotalCents / $totalLineItemSubtotalCents) * $billDiscountTotalCents
                    );
                    $remainingDiscountCents -= $lineItemDiscount;
                }

                if ($lineItemDiscount > 0) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => JournalEntryType::Credit,
                        'account_id' => Account::getPurchaseDiscountAccount()->id,
                        'amount' => CurrencyConverter::convertCentsToFormatSimple($lineItemDiscount),
                        'description' => "{$lineItemDescription} (Proportional Discount)",
                    ]);
                }
            }
        }
    }

    public function updateInitialTransaction(): void
    {
        $transaction = $this->initialTransaction;

        if ($transaction) {
            $transaction->delete();
        }

        $this->createInitialTransaction();
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
                'bill_number',
                'date',
                'due_date',
                'paid_at',
            ])
            ->modal(false)
            ->beforeReplicaSaved(function (self $original, self $replica) {
                $replica->status = BillStatus::Unpaid;
                $replica->bill_number = self::getNextDocumentNumber();
                $replica->date = now();
                $replica->due_date = now()->addDays($original->company->defaultBill->payment_terms->getDays());
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
                return BillResource::getUrl('edit', ['record' => $replica]);
            });
    }

    public function hasQuantityMismatch(): bool
    {
        $originalPO = $this->order;

        if (! $originalPO) {
            return false;
        }

        foreach ($originalPO->lineItems as $poItem) {
            $billItem = $this->lineItems->firstWhere('product_id', $poItem->product_id);

            if (! $billItem || $billItem->quantity < $poItem->quantity) {
                return true; // There's a mismatch
            }
        }

        return false; // No mismatch, no need for a backorder
    }

    public function createBackOrderIfNeeded(): void
    {
        $originalPO = $this->order; // Retrieve the associated Purchase Order
        if (! $originalPO) {
            return; // Exit if there is no associated PO
        }

        $backOrderItems = [];

        foreach ($originalPO->lineItems as $poItem) {
            $billItem = $this->lineItems->firstWhere('product_id', $poItem->product_id);

            if ($billItem && $billItem->quantity < $poItem->quantity) {
                $shortfall = $poItem->quantity - $billItem->quantity;

                $backOrderItems[] = [
                    'product_id' => $poItem->product_id,
                    'quantity' => $shortfall,
                    'unit_price' => $poItem->unit_price,
                ];
            }
        }

        if (! empty($backOrderItems)) {
            $this->createBackOrder($originalPO, $backOrderItems);
        }
    }

    protected function createBackOrder(Order $originalPO, array $backOrderItems): void
    {
        // Check if a backorder already exists for the given PO
        $existingBackOrder = Order::where('id', $originalPO->order_id)->first();

        if ($existingBackOrder) {
            throw new \RuntimeException('Back re Order has already been created.');
        }

        $order = $this->order()->create([
            'company_id' => $originalPO->company_id,
            'vendor_id' => $originalPO->vendor_id,
            'logo' => $originalPO->logo,
            'header' => $originalPO->header,
            'subheader' => $originalPO->subheader,
            'order_number' => Order::getNextDocumentNumber($this->company, 'BO-'),
            'reference_number' => $this->bill_number,
            'date' => now(),
            'expiration_date' => now()->addDays($this->company->defaultInvoice->payment_terms->getDays()),
            'status' => OrderStatus::Draft,
            'currency_code' => $originalPO->currency_code,
            'discount_method' => $originalPO->discount_method,
            'discount_computation' => $originalPO->discount_computation,
            'discount_rate' => $originalPO->discount_rate,
            'subtotal' => $originalPO->subtotal,
            'tax_total' => $originalPO->tax_total,
            'discount_total' => $originalPO->discount_total,
            'total' => $originalPO->total,
            'terms' => $originalPO->terms,
            'footer' => $originalPO->footer,
            'created_by' => $originalPO->created_by,
            'updated_by' => $originalPO->updated_by,
            'item_type' => $originalPO->item_type,
        ]);

        // Pass backorder items to ensure correct quantities are used
        $this->replicateLineItems($order, $backOrderItems);

    }

    public function replicateLineItems(Model $target, array $backOrderItems): void
    {
        $this->lineItems->each(function (DocumentLineItem $lineItem) use ($target, $backOrderItems) {

            // Find the matching product in the backorder items
            $backOrderItem = collect($backOrderItems)->firstWhere('product_id', $lineItem->product_id);

            $replica = $lineItem->replicate([
                'documentable_id',
                'documentable_type',
                'subtotal',
                'total',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ]);

            $replica->documentable_id = $target->id;
            $replica->documentable_type = $target->getMorphClass();

            // Only update quantity, keep original price
            $replica->quantity = $backOrderItem['quantity']; // Ensure this is validated in a form request

            $replica->save();

            $replica->adjustments()->sync($lineItem->adjustments->pluck('id'));
        });
    }
}
