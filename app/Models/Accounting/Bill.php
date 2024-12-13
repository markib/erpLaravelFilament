<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\TransactionType;
use App\Filament\Company\Resources\Purchases\BillResource;
use App\Models\Parties\Supplier;
use App\Observers\BillObserver;
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
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'amount_paid',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'status' => BillStatus::class,
        'subtotal' => MoneyCast::class,
        'tax_total' => MoneyCast::class,
        'discount_total' => MoneyCast::class,
        'total' => MoneyCast::class,
        'amount_paid' => MoneyCast::class,
        'amount_due' => MoneyCast::class,
    ];

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
        $transactionDescription = "Bill #{$this->bill_number}: Payment to {$this->vendor->name}";

        // Create transaction
        $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => $transactionType,
            'is_payment' => true,
            'posted_at' => $data['posted_at'],
            'amount' => $data['amount'],
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

        $transaction = $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => TransactionType::Journal,
            'posted_at' => $postedAt,
            'amount' => $this->total,
            'description' => 'Bill Creation for Bill #' . $this->bill_number,
        ]);

        $baseDescription = "{$this->vendor->name}: Bill #{$this->bill_number}";

        $transaction->journalEntries()->create([
            'company_id' => $this->company_id,
            'type' => JournalEntryType::Credit,
            'account_id' => Account::getAccountsPayableAccount()->id,
            'amount' => $this->total,
            'description' => $baseDescription,
        ]);

        foreach ($this->lineItems as $lineItem) {
            $lineItemDescription = "{$baseDescription} › {$lineItem->offering->name}";

            $transaction->journalEntries()->create([
                'company_id' => $this->company_id,
                'type' => JournalEntryType::Debit,
                'account_id' => $lineItem->offering->expense_account_id,
                'amount' => $lineItem->subtotal,
                'description' => $lineItemDescription,
            ]);

            foreach ($lineItem->adjustments as $adjustment) {
                if ($adjustment->isNonRecoverablePurchaseTax()) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => JournalEntryType::Debit,
                        'account_id' => $lineItem->offering->expense_account_id,
                        'amount' => $lineItem->calculateAdjustmentTotal($adjustment)->getAmount(),
                        'description' => "{$lineItemDescription} ({$adjustment->name})",
                    ]);
                } elseif ($adjustment->account_id) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => $adjustment->category->isDiscount() ? JournalEntryType::Credit : JournalEntryType::Debit,
                        'account_id' => $adjustment->account_id,
                        'amount' => $lineItem->calculateAdjustmentTotal($adjustment)->getAmount(),
                        'description' => $lineItemDescription,
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
}
