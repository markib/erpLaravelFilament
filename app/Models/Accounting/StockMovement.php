<?php

namespace App\Models\Accounting;

use App\Concerns\CompanyOwned;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use CompanyOwned;
    use HasFactory;

    protected $fillable = ['product_id', 'quantity', 'type', 'bill_id', 'invoice_id', 'company_id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
