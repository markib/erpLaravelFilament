<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Adjustmentable extends Model
{
    protected $table = 'adjustmentables';

     protected $fillable = [
        'adjustment_id',
        'adjustmentable_id',
        'adjustmentable_type',
        'created_at',
        'updated_at'
    ];

    public function adjustments(): MorphMany
    {
        return $this->morphMany(Adjustment::class, 'adjustmentable');
    }
}
