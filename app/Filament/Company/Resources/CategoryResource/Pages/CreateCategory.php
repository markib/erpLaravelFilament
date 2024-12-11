<?php

namespace App\Filament\Company\Resources\CategoryResource\Pages;

use App\Filament\Company\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
