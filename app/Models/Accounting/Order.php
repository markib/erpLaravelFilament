<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Casts\RateCast;
use App\Collections\Accounting\DocumentCollection;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\OrderStatus;
use App\Filament\Company\Resources\Purchases\BillResource;
use App\Filament\Company\Resources\Purchases\OrderResource;
use App\Models\Parties\Supplier;
use App\Models\Setting\Currency;
use App\Observers\OrderObserver;
use Filament\Actions\Action;
use Filament\Actions\MountableAction;
use Filament\Actions\ReplicateAction;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

#[CollectedBy(DocumentCollection::class)]
#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'vendor_id',
        'logo',
        'header',
        'subheader',
        'order_number',
        'reference_number',
        'date',
        'expiration_date',
        'approved_at',
        'accepted_at',
        'converted_at',
        'declined_at',
        'last_sent_at',
        'last_viewed_at',
        'status',
        'currency_code',
        'discount_method',
        'discount_computation',
        'discount_rate',
        'item_type',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'terms',
        'footer',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'expiration_date' => 'date',
        'approved_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'status' => OrderStatus::class,
        'discount_method' => DocumentDiscountMethod::class,
        'discount_computation' => AdjustmentComputation::class,
        'discount_rate' => RateCast::class,
        'subtotal' => MoneyCast::class,
        'tax_total' => MoneyCast::class,
        'discount_total' => MoneyCast::class,
        'total' => MoneyCast::class,
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class);
    }

    public function lineItems(): MorphMany
    {
        return $this->morphMany(DocumentLineItem::class, 'documentable');
    }

    protected function isCurrentlyExpired(): Attribute
    {
        return Attribute::get(function () {
            return $this->expiration_date?->isBefore(today()) && $this->canBeExpired();
        });
    }

    public function isDraft(): bool
    {
        return $this->status === OrderStatus::Draft;
    }

    public function wasApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function wasAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function wasDeclined(): bool
    {
        return $this->declined_at !== null;
    }

    public function wasConverted(): bool
    {
        return $this->converted_at !== null;
    }

    public function hasBeenSent(): bool
    {
        return $this->last_sent_at !== null;
    }

    public function hasBeenViewed(): bool
    {
        return $this->last_viewed_at !== null;
    }

    public function canBeExpired(): bool
    {
        return ! in_array($this->status, [
            OrderStatus::Draft,
            OrderStatus::Accepted,
            OrderStatus::Declined,
            OrderStatus::Converted,
        ]);
    }

    public function canBeApproved(): bool
    {
        return $this->isDraft() && ! $this->wasApproved();
    }

    public function canBeConverted(): bool
    {
        return $this->wasAccepted() && ! $this->wasConverted();
    }

    public function canBeMarkedAsDeclined(): bool
    {
        return $this->hasBeenSent()
            && ! $this->wasDeclined()
            && ! $this->wasConverted()
            && ! $this->wasAccepted();
    }

    public function canBeMarkedAsSent(): bool
    {
        return ! $this->hasBeenSent();
    }

    public function canBeMarkedAsAccepted(): bool
    {
        return $this->hasBeenSent()
            && ! $this->wasAccepted()
            && ! $this->wasDeclined()
            && ! $this->wasConverted();
    }

    public function hasLineItems(): bool
    {
        return $this->lineItems()->exists();
    }

    // public function hasInitialTransaction(): bool
    // {
    //     return $this->initialTransaction()->exists();
    // }

    // public function initialTransaction(): HasOne
    // {
    //     return $this->hasOne(Bill::class);
    // }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::Unsent,
            OrderStatus::Sent,
            OrderStatus::Viewed,
            OrderStatus::Accepted,
        ]);
    }

    public static function getNextDocumentNumber(): string
    {
        $company = auth()->user()->currentCompany;

        if (! $company) {
            throw new \RuntimeException('No current company is set for the user.');
        }

        $defaultOderSettings = $company->defaultInvoice;

        $numberPrefix = 'ORD-';
        $numberDigits = $defaultOderSettings->number_digits;

        $latestDocument = static::query()
            ->whereNotNull('order_number')
            ->latest('order_number')
            ->first();

        $lastNumberNumericPart = $latestDocument
            ? (int) substr($latestDocument->order_number, strlen($numberPrefix))
            : 0;

        $numberNext = $lastNumberNumericPart + 1;

        return $defaultOderSettings->getNumberNext(
            padded: true,
            format: true,
            prefix: $numberPrefix,
            digits: $numberDigits,
            next: $numberNext
        );
    }

    public function approveDraft(?Carbon $approvedAt = null): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('Order is not in draft status.');
        }

        $approvedAt ??= now();

        $this->update([
            'approved_at' => $approvedAt,
            'status' => OrderStatus::Unsent,
        ]);
    }

    public static function getApproveDraftAction(string $action = Action::class): MountableAction
    {
        return $action::make('approveDraft')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->visible(function (self $record) {
                return $record->canBeApproved();
            })
            ->databaseTransaction()
            ->successNotificationTitle('Order Approved')
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
                return $record->canBeMarkedAsSent();
            })
            ->successNotificationTitle('Order Sent')
            ->action(function (self $record, MountableAction $action) {
                $record->markAsSent();

                $action->success();
            });
    }

    public function markAsSent(?Carbon $sentAt = null): void
    {
        $sentAt ??= now();

        $this->update([
            'status' => OrderStatus::Sent,
            'last_sent_at' => $sentAt,
        ]);
    }

    public function markAsViewed(?Carbon $viewedAt = null): void
    {
        $viewedAt ??= now();

        $this->update([
            'status' => OrderStatus::Viewed,
            'last_viewed_at' => $viewedAt,
        ]);
    }

    public static function getReplicateAction(string $action = ReplicateAction::class): MountableAction
    {
        return $action::make()
            ->excludeAttributes([
                'order_number',
                'date',
                'expiration_date',
                'approved_at',
                'accepted_at',
                'converted_at',
                'declined_at',
                'last_sent_at',
                'last_viewed_at',
                'status',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->modal(false)
            ->beforeReplicaSaved(function (self $original, self $replica) {
                $replica->status = OrderStatus::Draft;
                $replica->order_number = self::getNextDocumentNumber();
                $replica->date = now();
                $replica->expiration_date = now()->addDays($original->company->defaultInvoice->payment_terms->getDays());
            })
            ->databaseTransaction()
            ->after(function (self $original, self $replica) {
                $original->replicateLineItems($replica);
            })
            ->successRedirectUrl(static function (self $replica) {
                return OrderResource::getUrl('edit', ['record' => $replica]);
            });
    }

    public static function getMarkAsAcceptedAction(string $action = Action::class): MountableAction
    {
        return $action::make('markAsAccepted')
            ->label('Mark as Accepted')
            ->icon('heroicon-o-check-badge')
            ->visible(static function (self $record) {
                return $record->canBeMarkedAsAccepted();
            })
            ->databaseTransaction()
            ->successNotificationTitle('Order Accepted')
            ->action(function (self $record, MountableAction $action) {
                $record->markAsAccepted();

                $action->success();
            });
    }

    public function markAsAccepted(?Carbon $acceptedAt = null): void
    {
        $acceptedAt ??= now();

        $this->update([
            'status' => OrderStatus::Accepted,
            'accepted_at' => $acceptedAt,
        ]);
    }

    public static function getMarkAsDeclinedAction(string $action = Action::class): MountableAction
    {
        return $action::make('markAsDeclined')
            ->label('Mark as Declined')
            ->icon('heroicon-o-x-circle')
            ->visible(static function (self $record) {
                return $record->canBeMarkedAsDeclined();
            })
            ->color('danger')
            ->requiresConfirmation()
            ->databaseTransaction()
            ->successNotificationTitle('Order Declined')
            ->action(function (self $record, MountableAction $action) {
                $record->markAsDeclined();

                $action->success();
            });
    }

    public function markAsDeclined(?Carbon $declinedAt = null): void
    {
        $declinedAt ??= now();

        $this->update([
            'status' => OrderStatus::Declined,
            'declined_at' => $declinedAt,
        ]);
    }

    public static function getConvertToBillAction(string $action = Action::class): MountableAction
    {
        return $action::make('convertToBill')
            ->label('Convert to Bill')
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->visible(static function (self $record) {
                return $record->canBeConverted();
            })
            ->databaseTransaction()
            ->successNotificationTitle('Order Converted to Bill')
            ->action(function (self $record, MountableAction $action) {
                $record->convertToBill();

                $action->success();
            })
            ->successRedirectUrl(static function (self $record) {

                return BillResource::getUrl('edit', ['record' => $record->refresh()->bill]);
            });
    }

    public function convertToBill(?Carbon $convertedAt = null): void
    {
        if ($this->order) {
            throw new \RuntimeException('Order has already been converted to an bill.');
        }

        $bill = $this->bill()->create([
            'company_id' => $this->company_id,
            'vendor_id' => $this->vendor_id,
            'logo' => $this->logo,
            'header' => $this->company->defaultInvoice->header,
            'subheader' => $this->company->defaultInvoice->subheader,
            'bill_number' => Bill::getNextDocumentNumber($this->company),
            'date' => now(),
            'due_date' => now()->addDays($this->company->defaultInvoice->payment_terms->getDays()),
            'status' => BillStatus::Draft,
            'currency_code' => $this->currency_code,
            'discount_method' => $this->discount_method,
            'discount_computation' => $this->discount_computation,
            'discount_rate' => $this->discount_rate,
            'subtotal' => $this->subtotal,
            'tax_total' => $this->tax_total,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'terms' => $this->terms,
            'footer' => $this->footer,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'item_type' => $this->item_type,
            'order_id' => $this->id,
        ]);

        $this->replicateLineItems($bill);

        $convertedAt ??= now();

        $this->update([
            'status' => OrderStatus::Converted,
            'converted_at' => $convertedAt,
        ]);
    }

    public function replicateLineItems(Model $target): void
    {
        $this->lineItems->each(function (DocumentLineItem $lineItem) use ($target) {
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
            $replica->save();

            $replica->adjustments()->sync($lineItem->adjustments->pluck('id'));
        });
    }
}
