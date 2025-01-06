<?php

namespace App\Models\Product;

use App\Casts\MoneyCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Concerns\HasDefault;
use App\Concerns\SyncsWithCompanyDefaults;
use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Adjustment;
use App\Observers\ProductObserver;
use Database\Factories\Product\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[ObservedBy(ProductObserver::class)]
class Product extends Model
{
    use Blamable;
    use CompanyOwned;

    // use HasDefault;
    use HasFactory;
    use SyncsWithCompanyDefaults;

    protected $table = 'products';

    protected $fillable = [
        'company_id',
        'category_id',
        'product_name',
        'product_code',
        'sku',
        'product_price',
        'product_cost',
        'product_quantity',
        'product_unit',
        'inventory_account_id',
        'income_account_id',
        'expense_account_id',
        'sellable',
        'purchasable',
        'product_order_tax',
        'product_tax_type',
        'product_stock_alert',
        'product_barcode_symbology',
        'product_note',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        // 'price' => MoneyCast::class,
        'sellable' => 'boolean',
        'purchasable' => 'boolean',
    ];

    protected $appends = [
        'formatted_price',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    protected function formattedPrice(): Attribute
    {
        return Attribute::get(fn ($value, $attributes) => number_format($attributes['product_price'], 2));
    }

    // Add relationship for inventory account
    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id')->where('subtype_id', 3);
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
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

    public function adjustments(): MorphToMany
    {
        return $this->morphToMany(Adjustment::class, 'adjustmentable', 'adjustmentables');
    }

    public function salesAdjustments(): MorphToMany
    {
        return $this->adjustments()->where('type', AdjustmentType::Sales);
    }

    public function purchaseAdjustments(): MorphToMany
    {
        return $this->adjustments()->where('type', AdjustmentType::Purchase);
    }

    public function clearSellableAdjustments(): void
    {
        if (! $this->sellable) {
            $this->income_account_id = null;

            $adjustmentIds = $this->salesAdjustments()->pluck('adjustment_id');

            $this->adjustments()->detach($adjustmentIds);
        }
    }

    public function clearPurchasableAdjustments(): void
    {
        if (! $this->purchasable) {
            $this->expense_account_id = null;

            $adjustmentIds = $this->purchaseAdjustments()->pluck('adjustment_id');

            $this->adjustments()->detach($adjustmentIds);
        }
    }

    // Add any additional inventory-related methods here
    public function updateStock($quantity, $type = 'add')
    {
        if ($type === 'add') {
            $this->increment('product_quantity', $quantity);
        } else {
            $this->decrement('product_quantity', $quantity);
        }
    }

    public function isLowStock()
    {
        return $this->product_quantity <= $this->product_stock_alert;
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }
}
