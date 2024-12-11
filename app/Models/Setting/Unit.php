<?php

namespace App\Models\Setting;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Concerns\HasDefault;
use App\Concerns\SyncsWithCompanyDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasDefault;
    use HasFactory;
    use SyncsWithCompanyDefaults;

    protected $table = 'units';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'short_name',
        'operator',
        'operation_value',
    ];

    protected $casts = [
        'enabled' => 'boolean'
    ];

    
}
