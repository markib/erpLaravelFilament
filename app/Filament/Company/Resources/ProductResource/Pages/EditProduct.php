<?php

namespace App\Filament\Company\Resources\ProductResource\Pages;

use App\Filament\Company\Resources\ProductResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
            
        return Notification::make()
        ->success()
        ->title('Product updated')
        ->body('The product has been saved successfully.');
    }


    // protected  function mutateFormDataBeforeSave(array $data): array
    // {
    //     // dd($data);
    //     Log::info('Data before saving: ', $data);

    //     // Set 'enabled' field to true only if not set
    //     // if (!isset($data['enabled'])) {
    //     //     $data['enabled'] = true;  // Default value for 'enabled' field
    //     // }
    //     $data['enabled'] = (bool) ($data['enabled'] ?? false);
    //     Log::info('Saving new product with data:', $data);
    //     return $data;
    // }

    /**
     * Perform actions after saving the record
     */
    // protected function beforeSave(): void
    // {


    //     // Check if the record was recently created
    //     if ($this->record->wasRecentlyCreated) {
    //         // If the record is newly created
    //         Log::info('A new product was created: ' . $this->record->product_name);

    //         // Ensure 'enabled' is set to true if not provided
    //         // if (!isset($this->record->enabled)) {
    //         //     $this->record->enabled = true;  // Default value for 'enabled' field
    //         // }
    //     } else {
    //         // If the record was updated
    //         Log::info('Product has been updated: ' . $this->record->product_name);

    //         // Only set default for 'enabled' if not explicitly provided (i.e., null or missing)
    //         // if (!array_key_exists('enabled', $this->record->getDirty())) {
    //         //     if (!isset($this->record->enabled)) {
    //         //         $this->record->enabled = false;  // Default value for 'enabled' field
    //         //     }
    //         // }
    //         // Log the product status (enabled/disabled)
    //         Log::info('Product is enabled: ' . ($this->record->enabled ? 'Yes' : 'No'));
    //     }
    //     // You can perform additional actions here as needed, such as notifying users
    // }

    // protected function afterSave(): void
    // {
    //     // Runs after the form fields are saved to the database.
    //     Log::info('Product after Save: ' . $this->record->product_name);
    //     Log::info('Product enabled status after save: ' . ($this->record->enabled ? 'Enabled' : 'Disabled'));

    // }
}
