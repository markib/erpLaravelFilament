<?php

namespace App\Models\Product;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Concerns\HasDefault;
use App\Concerns\SyncsWithCompanyDefaults;
use Database\Factories\Product\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


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
        'product_price',
        'product_cost',
        'product_quantity',
        'product_unit',
        'prduct_order_tax',
        'product_tax_type',
        'product_stock_alert',
        'product_barcode_symbology',
        'prduct_note',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
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
        return Attribute::get(fn($value, $attributes) => number_format($attributes['product_price'], 2));
    }

    protected static function newFactory(): Factory
    {
         return ProductFactory::new();
    }
 

}
