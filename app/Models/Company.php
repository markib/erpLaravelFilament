<?php

namespace App\Models;

use App\Enums\Setting\DocumentType;
use App\Models\Accounting\AccountSubtype;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\Order;
use App\Models\Accounting\StockMovement;
use App\Models\Banking\BankAccount;
use App\Models\Banking\ConnectedBankAccount;
use App\Models\Common\Contact;
use App\Models\Common\Offering;
use App\Models\Core\Department;
use App\Models\Parties\Customer;
use App\Models\Parties\Supplier;
use App\Models\Product\Product;
use App\Models\Setting\Appearance;
use App\Models\Setting\CompanyDefault;
use App\Models\Setting\CompanyProfile;
use App\Models\Setting\Currency;
use App\Models\Setting\Discount;
use App\Models\Setting\DocumentDefault;
use App\Models\Setting\Localization;
use App\Models\Setting\Tax;
use App\Models\Setting\Unit;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Wallo\FilamentCompanies\Company as FilamentCompaniesCompany;
use Wallo\FilamentCompanies\Events\CompanyCreated;
use Wallo\FilamentCompanies\Events\CompanyDeleted;
use Wallo\FilamentCompanies\Events\CompanyUpdated;

class Company extends FilamentCompaniesCompany implements HasAvatar
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'personal_company' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_company',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => CompanyCreated::class,
        'updated' => CompanyUpdated::class,
        'deleted' => CompanyDeleted::class,
    ];

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->profile->logo_url ?? $this->owner->profile_photo_url;
    }

    public function connectedBankAccounts(): HasMany
    {
        return $this->hasMany(ConnectedBankAccount::class, 'company_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Accounting\Account::class, 'company_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'company_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Accounting\Bill::class, 'company_id');
    }

    public function appearance(): HasOne
    {
        return $this->hasOne(Appearance::class, 'company_id');
    }

    public function accountSubtypes(): HasMany
    {
        return $this->hasMany(AccountSubtype::class, 'company_id');

    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'company_id');
    }

    public function currencies(): HasMany
    {
        return $this->hasMany(Currency::class, 'company_id');
    }

    public function default(): HasOne
    {
        return $this->hasOne(CompanyDefault::class, 'company_id');
    }

    public function defaultBill(): HasOne
    {
        return $this->hasOne(DocumentDefault::class, 'company_id')
            ->where('type', DocumentType::Bill);
    }

    public function defaultInvoice(): HasOne
    {
        return $this->hasOne(DocumentDefault::class, 'company_id')
            ->where('type', DocumentType::Invoice);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Accounting\Invoice::class, 'company_id');
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class, 'company_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'company_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'company_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class, 'company_id');
    }

    public function locale(): HasOne
    {
        return $this->hasOne(Localization::class, 'company_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class, 'company_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'company_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Accounting\Transaction::class, 'company_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function DocumentDefaults(): HasOne
    {
        return $this->HasOne(DocumentDefault::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class, 'company_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class, 'company_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'company_id');
    }
}
