<?php

namespace App\Models\Setting;

use App\Casts\RateCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Concerns\HasDefault;
use App\Concerns\SyncsWithCompanyDefaults;
use App\Enums\Setting\TaxComputation;
use App\Enums\Setting\TaxScope;
use App\Enums\Setting\TaxType;
use Database\Factories\Setting\TaxFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tax extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasDefault;
    use HasFactory;
    use SyncsWithCompanyDefaults;

    protected $table = 'taxes';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'rate',
        'computation',
        'type',
        'scope',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rate' => RateCast::class,
        'computation' => TaxComputation::class,
        'type' => TaxType::class,
        'scope' => TaxScope::class,
        'enabled' => 'boolean',
    ];

    protected ?string $evaluatedDefault = 'type';

    public function defaultSalesTax(): HasOne
    {
        return $this->hasOne(CompanyDefault::class, 'sales_tax_id');
    }

    public function defaultPurchaseTax(): HasOne
    {
        return $this->hasOne(CompanyDefault::class, 'purchase_tax_id');
    }

    protected static function newFactory(): Factory
    {
        return TaxFactory::new();
    }
}
