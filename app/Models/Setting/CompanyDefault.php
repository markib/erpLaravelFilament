<?php

namespace App\Models\Setting;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Setting\DiscountType;
use App\Enums\Setting\TaxType;
use App\Models\Banking\BankAccount;
use Database\Factories\Setting\CompanyDefaultFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyDefault extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'company_defaults';

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'currency_code',
        'sales_tax_id',
        'purchase_tax_id',
        'sales_discount_id',
        'purchase_discount_id',
        'created_by',
        'updated_by',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function salesTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'sales_tax_id', 'id')
            ->where('type', TaxType::Sales);
    }

    public function purchaseTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'purchase_tax_id', 'id')
            ->where('type', TaxType::Purchase);
    }

    public function salesDiscount(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'sales_discount_id', 'id')
            ->where('type', DiscountType::Sales);
    }

    public function purchaseDiscount(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'purchase_discount_id', 'id')
            ->where('type', DiscountType::Purchase);
    }

    protected static function newFactory(): Factory
    {
        return CompanyDefaultFactory::new();
    }
}
