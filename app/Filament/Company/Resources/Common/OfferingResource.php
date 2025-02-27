<?php

namespace App\Filament\Company\Resources\Common;

use App\Enums\Accounting\AccountCategory;
use App\Enums\Accounting\AccountType;
use App\Enums\Common\OfferingType;
use App\Filament\Company\Resources\Common\OfferingResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Common\Offering;
use App\Models\Product\Product;
use App\Utilities\Currency\CurrencyAccessor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use JaOcero\RadioDeck\Forms\Components\RadioDeck;

class OfferingResource extends Resource
{
    protected static ?string $model = Offering::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public $type = 'product';

    public static function form(Form $form): Form
    {

        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        RadioDeck::make('type')
                            ->options(OfferingType::class)
                            ->default(OfferingType::Product)
                            ->icons(OfferingType::class)
                            ->required()
                            ->live()
                            ->extraCardsAttributes(fn ($state): array => [
                                'class' => 'custom-card peer-checked:border-blue-500 peer-checked:bg-blue-100 dark:peer-checked:border-blue-300 dark:peer-checked:bg-blue-900',
                            ])
                            ->columns(),
                        // Group these fields together for better layout
                        Forms\Components\Fieldset::make('Details')
                            ->schema([
                                // Forms\Components\Select::make('name')
                                //     ->label('Name')
                                //     ->options(Product::where('enabled', true)->orderby('product_name')->pluck('product_name', 'product_name')->toArray()) // Fetch product list
                                //     ->searchable()
                                //     ->required()
                                //     ->reactive() // Makes the field reactive
                                //     ->afterStateUpdated(function (Forms\Set $set, $state) {
                                //         // Fetch product price and set it to the price field
                                //         $product = Product::where('product_name', $state)->first();
                                //         if ($product) {
                                //             $set('price', $product->product_price);
                                //             $set('description', $product->product_note);
                                //             $set('quantity', $product->product_quantity);
                                //         }
                                //     }),
                                Forms\Components\TextInput::make('name')
                                    ->autofocus()
                                    ->required()
                                    ->columnStart(1)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('price')
                                    ->label('Price')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->rules(['numeric', 'min:0.01']),
                                //         Forms\Components\TextInput::make('quantity')
                                //             ->label('Quantity')
                                //             ->numeric()
                                //             ->live()
                                //             ->required()
                                //             ->default(0)
                                //             ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                //                 $product = Product::where('product_name', $get('name'))->first();
                                //                 if ($state > $product->product_quantity) {
                                //                     $set('quantity', $state); // Reset to the available quantity
                                //                     Notification::make()
                                //                         ->title('Quantity exceeds available stock')
                                //                         ->danger()
                                //                         ->send();
                                //                 }
                                //             })->rules(function (Get $get) {
                                //     $productName = $get('name');
                                //     if (!$productName) {
                                //         return [];
                                //     }

                                //     $product = Product::where('product_name', $productName)->first();

                                //     if (!$product) {
                                //         return [];
                                //     }

                                //     return [
                                //         'max:'.$product->product_quantity,
                                //     ];
                                // })
                                //             ->validationMessages([
                                //                 'max' => 'The quantity cannot exceed the available stock.'
                                //             ]),

                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->columnSpan(2)
                                    ->rows(3),
                            ])
                            ->columns(2), // Organize fields in two columns if desired
                        Forms\Components\CheckboxList::make('attributes')
                            ->options([
                                'Sellable' => 'Sellable',
                                'Purchasable' => 'Purchasable',
                            ])
                            ->hiddenLabel()
                            ->required()
                            ->live()
                            ->bulkToggleable()
                            ->validationMessages([
                                'required' => 'The offering must be either sellable or purchasable.',
                            ]),
                    ])->columns(),
                // Sellable Section
                Forms\Components\Section::make('Sale Information')
                    ->schema([
                        Forms\Components\Select::make('income_account_id')
                            ->label('Income Account')
                            ->options(Account::query()
                                ->where('category', AccountCategory::Revenue)
                                ->where('type', AccountType::OperatingRevenue)
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'The income account is required for sellable offerings.',
                            ]),
                        Forms\Components\Select::make('salesTaxes')
                            ->label('Sales Tax')
                            ->relationship('salesTaxes', 'name')
                            ->preload()
                            ->multiple(),
                        Forms\Components\Select::make('salesDiscounts')
                            ->label('Sales Discount')
                            ->relationship('salesDiscounts', 'name')
                            ->preload()
                            ->multiple(),
                    ])
                    ->columns()
                    ->visible(fn (Forms\Get $get) => in_array('Sellable', $get('attributes') ?? [])),

                // Purchasable Section
                Forms\Components\Section::make('Purchase Information')
                    ->schema([
                        Forms\Components\Select::make('expense_account_id')
                            ->label('Expense Account')
                            ->options(Account::query()
                                ->where('category', AccountCategory::Expense)
                                ->where('type', AccountType::OperatingExpense)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'The expense account is required for purchasable offerings.',
                            ]),
                        Forms\Components\Select::make('purchaseTaxes')
                            ->label('Purchase Tax')
                            ->relationship('purchaseTaxes', 'name')
                            ->preload()
                            ->multiple(),
                        Forms\Components\Select::make('purchaseDiscounts')
                            ->label('Purchase Discount')
                            ->relationship('purchaseDiscounts', 'name')
                            ->preload()
                            ->multiple(),
                    ])
                    ->columns()
                    ->visible(fn (Forms\Get $get) => in_array('Purchasable', $get('attributes') ?? [])),
            ])->columns();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->selectRaw("
                        *,
                        CONCAT_WS(' & ',
                            CASE WHEN sellable THEN 'Sellable' END,
                            CASE WHEN purchasable THEN 'Purchasable' END
                        ) AS attributes
                    ");
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name'),
                Tables\Columns\TextColumn::make('attributes')
                    ->label('Attributes')
                    ->badge(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->currency(CurrencyAccessor::getDefaultCurrency(), true)
                    ->sortable()
                    ->description(function (Offering $record) {
                        $adjustments = $record->adjustments()
                            ->pluck('name')
                            ->join(', ');

                        if (empty($adjustments)) {
                            return null;
                        }

                        $adjustmentsList = Str::of($adjustments)->limit(40);

                        return "+ {$adjustmentsList}";
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfferings::route('/'),
            'create' => Pages\CreateOffering::route('/create'),
            'edit' => Pages\EditOffering::route('/{record}/edit'),
        ];
    }
}
