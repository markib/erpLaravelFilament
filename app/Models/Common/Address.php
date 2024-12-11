<?php

namespace App\Models\Common;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Common\AddressType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'addresses';

    protected $fillable = [
        'company_id',
        'type',
        'recipient',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => AddressType::class,
    ];

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}
