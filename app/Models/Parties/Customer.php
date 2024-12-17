<?php

namespace App\Models\Parties;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Concerns\SyncsWithCompanyDefaults;
use App\Models\Accounting\Invoice;
use App\Models\Setting\Currency;
use Database\Factories\Parties\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;
    use SyncsWithCompanyDefaults;

    protected $table = 'customers';

    protected $fillable = [
        'company_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address',
        'city',
        'country',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    protected static function newFactory(): Factory
    {
        return CustomerFactory::new();
    }
}
