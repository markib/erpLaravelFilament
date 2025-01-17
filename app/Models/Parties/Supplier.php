<?php

namespace App\Models\Parties;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Concerns\SyncsWithCompanyDefaults;
use App\Models\Accounting\Bill;
use App\Models\Locale\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;
    use SyncsWithCompanyDefaults;

    protected $table = 'suppliers';

    protected $fillable = [
        'company_id',
        'supplier_name',
        'supplier_email',
        'supplier_phone',
        'address',
        'city',
        'country',
        'currency_code',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
