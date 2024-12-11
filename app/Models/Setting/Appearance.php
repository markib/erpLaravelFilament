<?php

namespace App\Models\Setting;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Setting\Font;
use App\Enums\Setting\PrimaryColor;
use App\Enums\Setting\RecordsPerPage;
use App\Enums\Setting\TableSortDirection;
use Database\Factories\Setting\AppearanceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appearance extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'appearances';

    protected $fillable = [
        'company_id',
        'primary_color',
        'font',
        'table_sort_direction',
        'records_per_page',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'primary_color' => PrimaryColor::class,
        'font' => Font::class,
        'table_sort_direction' => TableSortDirection::class,
        'records_per_page' => RecordsPerPage::class,
    ];

    protected static function newFactory(): Factory
    {
        return AppearanceFactory::new();
    }
}
