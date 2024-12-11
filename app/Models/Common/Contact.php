<?php

namespace App\Models\Common;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Common\ContactType;
use App\Models\Setting\Currency;
use Database\Factories\Common\ContactFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Wallo\FilamentCompanies\FilamentCompanies;

class Contact extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'contacts';

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'email',
        'address',
        'city_id',
        'zip_code',
        'state_id',
        'country',
        'timezone',
        'language',
        'contact_method',
        'phone_number',
        'tax_id',
        'currency_code',
        'website',
        'reference',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => ContactType::class,
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function employeeship(): HasOne
    {
        return $this->hasOne(FilamentCompanies::employeeshipModel(), 'contact_id');
    }

    protected static function newFactory(): Factory
    {
        return ContactFactory::new();
    }
}
