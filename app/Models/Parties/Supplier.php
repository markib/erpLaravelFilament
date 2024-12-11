<?php

namespace App\Models\Parties;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Concerns\SyncsWithCompanyDefaults;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use Blamable;
    use CompanyOwned;
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
        'enabled',
        'created_by',
        'updated_by',
    ];

  
    protected $casts = [
        'enabled' => 'boolean',
    ];
}
