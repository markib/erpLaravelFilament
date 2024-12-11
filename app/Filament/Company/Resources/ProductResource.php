<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ProductRelationManagerResource\RelationManagers\CategoryRelationManager;
use App\Filament\Company\Resources\ProductResource\Pages;
use App\Filament\Company\Resources\ProductResource\RelationManagers;
use App\Models\Product\Categories;
use App\Models\Product\Product;
use App\Models\Setting\Unit;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Actions\Action as ActionsAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationGroup = 'Manage Products';
    protected static ?string $navigationLabel = 'Add Product';
    protected static ?int $navigationSort = 2;
    // protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    // Specify the relationship in the Company model
    protected static ?string $tenantRelationshipName = 'products';  // Set the relationship name here


    public static function form(Form $form): Form
    {
        return $form
            ->schema([Forms\Components\TextInput::make('product_name')
                ->label('Product Name')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('product_code')
                ->label('Product Code')
                ->unique(ignorable: fn($record) => $record)
                ->nullable(),

            Forms\Components\Select::make('category_id')
                ->label('Category')
                ->relationship('category', 'category_name')
                ->required()
                ->preload()
                ->createOptionForm([

                Forms\Components\TextInput::make('category_name')->required(),
                Forms\Components\TextInput::make('category_code')->required()

                ])
                ->createOptionUsing(function (array $data) {

                    return Categories::create($data)->id; // Return the ID of the newly created category

                }),
            Forms\Components\TextInput::make('product_barcode_symbology')
                ->required()
                ->label("Barcode Symbology"),
                
                Forms\Components\Select::make('product_unit')
                    ->required()
                    ->label("Unit")
                    ->options(Unit::pluck('name', 'id')->toArray()),
            Forms\Components\TextInput::make('product_cost')
                ->required()
                ->label('Cost')
                ->numeric()
                ->default(0),
            Forms\Components\TextInput::make('product_price')
                ->required()
                ->label('Price')
                ->numeric()
                ->default(0),

            Forms\Components\TextInput::make('product_quantity')
                ->required()
                ->label('Quantity')
                ->numeric()
                ->default(0),

            Forms\Components\TextInput::make('product_stock_alert')
                ->required()
                ->label('Stock Alert')
                ->numeric()
                ->default(0),
            Forms\Components\TextInput::make('product_order_tax')
                ->label('Tax(%)')
                ->numeric()
                ->default(0),
            Forms\Components\Select::make('product_tax_type')
                ->label("Tax Type")
                ->options(['Exclusive', 'Inclusive']),
            Forms\Components\Textarea::make('product_note')
                ->label('Note')
                ->maxLength(65535),
            Toggle::make('enabled')
                ->label('Enabled')
                ->onColor('success')
                ->offColor('danger')
                ->inline(false),
            // ->default(true),
            Forms\Components\Group::make([
                    Forms\Components\DatePicker::make('created_at')
                        ->label('Created At')
                        ->disabled()
                        ->visible(fn(Forms\Get $get): bool => filled($get('created_at')))
                        ->displayFormat('d-m-Y H:i:s'),
                        
                    Forms\Components\DatePicker::make('updated_at')
                        ->label('Updated At')
                        ->disabled()
                        ->visible(fn(Forms\Get $get): bool => filled($get('updated_at')))
                        ->displayFormat('d-m-Y H:i:s')
                        
            ])->columnSpan(1),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
            Tables\Columns\TextColumn::make('product_name')
                ->localizeLabel()
                ->label('Porduct Name')
            
                ->weight('semibold')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('product_code')
            ->localizeLabel()
                ->label('Product Code')
                
                ->weight('semibold')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('category.category_name')
            ->localizeLabel()
                ->label('Category')
                ->weight('semibold')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('product_price')
            ->localizeLabel()
            ->label('Price')
            ->money()
                ->weight('semibold')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('product_stock_alert')
            ->localizeLabel()
            ->label('Stock Alert')
                ->weight('semibold')
                ->alignment(Alignment::Center)
                ->sortable(),
            Tables\Columns\TextColumn::make('created_at')
            ->localizeLabel()
            ->label('Created At')
                ->weight('semibold')
                ->sortable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('updated_at')
            ->localizeLabel()
            ->label('Updated At')
                ->weight('semibold')
                ->sortable()
                ->toggleable(),
            Tables\Columns\IconColumn::make('enabled')
    ->label('Status')
    
    ->boolean() // Automatically handles displaying a check/cross icon
    ->action(fn($record) => $record->update(['enabled' => !$record->enabled])) // Toggle action
    ->color(fn($state) => $state ? 'success' : 'danger') // Change color based on state
    // ->tooltip(fn($state) => $state ? 'Click to disable' : 'Click to enable') // Tooltip for clarity
            ])

            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
            // ActionsAction::make('enable')
            //     ->label('Enable')
            //     ->icon('heroicon-o-check-circle')
            // ->action(function ($record) {
            //     // Explicitly target only this row
            //     // Product::where('id', $record->id)->update(['enabled' => 1]);
            //     // Enable the current row
            //     $record->update(['enabled' => 1]);
                
            // })
            //     ->color('success')
            //     ->visible(fn($record) => !$record->enabled), // Show only if disabled

            // ActionsAction::make('disable')
            //     ->label('Disable')
            //     ->icon('heroicon-o-x-circle')
            //     ->action(fn($record) => $record->update(['enabled' => 0]))
            //     ->color('danger')
            //     ->visible(fn($record) => $record->enabled), // Show only if enabled
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label(''),
                
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CategoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * Mutate form data before save (for both create and update operations)
     */
    // protected static function mutateFormDataBeforeSave(array $data): array
    // {
    //     Log::info('Data before saving: ', $data);

    //     // Set 'enabled' field to true only if not set
    //     if (!isset($data['enabled'])) {
    //         $data['enabled'] = true;  // Default value for 'enabled' field
    //     }
    //     Log::info('Saving new product with data:', $data);
    //     return $data;
    // }

    // protected  static function mutateFormDataBeforeCreate(array $data): array
    // {
    //     dd($data);
    //     // Ensure 'enabled' only applies to the new record
    //     $data['enabled'] = $data['enabled'] ?? true;

    //     Log::info('Creating new product with data:', $data);
    //     return $data;
    // }
}
