<?php

namespace App\Models\Accounting;

use Akaunting\Money\Money;
use App\Casts\DocumentMoneyCast;
use App\Casts\MoneyCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentType;
use App\Models\Common\Offering;
use App\Models\Product\Product;
use App\Observers\DocumentLineItemObserver;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\RateCalculator;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[ObservedBy(DocumentLineItemObserver::class)]
class DocumentLineItem extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'document_line_items';

    protected $fillable = [
        'company_id',
        'offering_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'tax_total',
        'discount_total',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'unit_price' => MoneyCast::class,
        'subtotal' => DocumentMoneyCast::class,
        'tax_total' => MoneyCast::class,
        'discount_total' => MoneyCast::class,
        'total' => MoneyCast::class,
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sellableOffering(): BelongsTo
    {
        return $this->offering()->where('sellable', true);
    }

    // public function sellableProduct(): BelongsTo
    // {
    //     return $this->product()->where('enabled', true);
    // }

    public function purchasableOffering(): BelongsTo
    {
        return $this->offering()->where('purchasable', true);
    }

    public function purchasableProducts(): BelongsTo
    {
        return $this->product()->where('purchasable', true);
    }

    public function adjustments(): MorphToMany
    {
        return $this->morphToMany(Adjustment::class, 'adjustmentable', 'adjustmentables');
    }

    public function salesTaxes(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Tax)->where('type', AdjustmentType::Sales);
    }

    public function purchaseTaxes(): MorphToMany
    {   
        return $this->adjustments()->where('category', AdjustmentCategory::Tax)->where('type', AdjustmentType::Purchase);
    }

    public function salesDiscounts(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Discount)->where('type', AdjustmentType::Sales);
    }

    public function purchaseDiscounts(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Discount)->where('type', AdjustmentType::Purchase);
    }

    public function taxes(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Tax);
    }

    public function discounts(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Discount);
    }

    public function calculateTaxTotal(): Money
    {
        $subtotal = money($this->subtotal, CurrencyAccessor::getDefaultCurrency());

        return $this->taxes->reduce(
            fn (Money $carry, Adjustment $tax) => $carry->add($subtotal->multiply($tax->rate / 100)),
            money(0, CurrencyAccessor::getDefaultCurrency())
        );
    }

    public function calculateDiscountTotal(): Money
    {
        // $subtotal = money($this->subtotal, CurrencyAccessor::getDefaultCurrency());

        // return $this->discounts->reduce(
        //     fn (Money $carry, Adjustment $discount) => $carry->add($subtotal->multiply($discount->rate / 100)),
        //     money(0, CurrencyAccessor::getDefaultCurrency())
        // );
        $subtotalInCents = money($this->subtotal, CurrencyAccessor::getDefaultCurrency())->getAmount();

        return $this->discounts->reduce(
            function (Money $carry, Adjustment $discount) use ($subtotalInCents) {
                $discountAmount = 0;

                if ($discount->computation->isPercentage()) {
                    // Calculate percentage-based discount
                    $discountAmount = RateCalculator::calculatePercentage($subtotalInCents, $discount->getRawOriginal('rate'));
                } else {
                    // Fixed discount amount
                    $discountAmount = $discount->rate;
                }

                return $carry->add(money($discountAmount, CurrencyAccessor::getDefaultCurrency()));
            },
            money(0, CurrencyAccessor::getDefaultCurrency())
        );
    }

    public function calculateAdjustmentTotal(Adjustment $adjustment): Money
    {
        $subtotal = money($this->subtotal, CurrencyAccessor::getDefaultCurrency());

        return $subtotal->multiply($adjustment->rate / 100);
    }

    public function calculateTaxTotalAmount(): int
    {
        $subtotalInCents = $this->getRawOriginal('subtotal');

        return $this->taxes->reduce(function (int $carry, Adjustment $tax) use ($subtotalInCents) {
            if ($tax->computation->isPercentage()) {
                $scaledRate = $tax->getRawOriginal('rate');

                return $carry + RateCalculator::calculatePercentage($subtotalInCents, $scaledRate);
            } else {
                return $carry + $tax->getRawOriginal('rate');
            }
        }, 0);
    }

    public function calculateDiscountTotalAmount(): int
    {
        $subtotalInCents = $this->getRawOriginal('subtotal');

        return $this->discounts->reduce(function (int $carry, Adjustment $discount) use ($subtotalInCents) {
            if ($discount->computation->isPercentage()) {
                $scaledRate = $discount->getRawOriginal('rate');

                return $carry + RateCalculator::calculatePercentage($subtotalInCents, $scaledRate);
            } else {
                return $carry + $discount->getRawOriginal('rate');
            }
        }, 0);
    }

    public function calculateAdjustmentTotalAmount(Adjustment $adjustment): int
    {
        $subtotalInCents = $this->getRawOriginal('subtotal');

        if ($adjustment->computation->isPercentage()) {
            $scaledRate = $adjustment->getRawOriginal('rate');

            return RateCalculator::calculatePercentage($subtotalInCents, $scaledRate);
        } else {
            return $adjustment->getRawOriginal('rate');
        }
    }
}
