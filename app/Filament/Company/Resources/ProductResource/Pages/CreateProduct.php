<?php

namespace App\Filament\Company\Resources\ProductResource\Pages;

use App\Filament\Company\Resources\ProductResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getCreatedNotification(): ?Notification
    {
       
        return Notification::make()
            ->success()
            ->title('Product created')
            ->body('The product has been create successfully.');
    }

    /**
     * Return the current page URL to keep the user on the same page after saving
     */
    // protected function getRedirectUrl(): string
    // {
    //     return $this->getResource()::getUrl('create');  // This keeps the user on the create page
    // }

    /**
     * Perform actions after saving the record (for newly created products)
     */
    // protected function beforeCreate(): void
    // {
    //     // $data['enabled'] = (bool) ($data['enabled'] ?? false);

    //     // Custom logic after the record is saved (for create)
    //     Log::info('Preparing to create a new product with data: ', $this->data);

    //     // Perform other actions if needed
    // }
}
