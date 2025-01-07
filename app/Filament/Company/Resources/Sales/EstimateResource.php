<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\EstimateStatus;
use App\Enums\Common\ItemType;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages;
use App\Filament\Company\Resources\Sales\EstimateResource\Widgets;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\DocumentTotals;
use App\Filament\Tables\Actions\ReplicateBulkAction;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Estimate;
use App\Models\Common\Offering;
use App\Models\Parties\Customer;
use App\Models\Product\Product;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EstimateResource extends Resource
{
    protected static ?string $model = Estimate::class;

    // protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        return $form
            ->schema([
                Forms\Components\Section::make('Estimate Header')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Split::make([
                            Forms\Components\Group::make([
                                FileUpload::make('logo')
                                    ->openable()
                                    ->maxSize(1024)
                                    ->localizeLabel()
                                    ->visibility('public')
                                    ->disk('public')
                                    ->directory('logos/document')
                                    ->imageResizeMode('contain')
                                    ->imageCropAspectRatio('3:2')
                                    ->panelAspectRatio('3:2')
                                    ->maxWidth(MaxWidth::ExtraSmall)
                                    ->panelLayout('integrated')
                                    ->removeUploadedFileButtonPosition('center bottom')
                                    ->uploadButtonPosition('center bottom')
                                    ->uploadProgressIndicatorPosition('center bottom')
                                    ->getUploadedFileNameForStorageUsing(
                                        static fn (TemporaryUploadedFile $file): string => (string) str($file->getClientOriginalName())
                                            ->prepend(Auth::user()->currentCompany->id . '_'),
                                    )
                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif']),
                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('header')
                                    ->default('Estimate'),
                                Forms\Components\TextInput::make('subheader'),
                                Forms\Components\View::make('filament.forms.components.company-info')
                                    ->viewData([
                                        'company_name' => $company->name,
                                        'company_address' => $company->profile->address,
                                        'company_city' => $company->profile->city?->name,
                                        'company_state' => $company->profile->state?->name,
                                        'company_zip' => $company->profile->zip_code,
                                        'company_country' => $company->profile->state?->country->name,
                                    ]),
                            ])->grow(true),
                        ])->from('md'),
                    ]),
                Forms\Components\Section::make('Estimate Details')
                    ->schema([
                        Forms\Components\Split::make([
                            Forms\Components\Group::make([
                                Forms\Components\Select::make('client_id')
                                    ->relationship('client', 'customer_name')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $currencyCode = Customer::find($state)?->currency_code;

                                        if ($currencyCode) {
                                            $set('currency_code', $currencyCode);
                                        }
                                    }),
                                CreateCurrencySelect::make('currency_code'),
                                Forms\Components\Placeholder::make('')->extraAttributes(['class' => 'h-32 mt-8']),

                                Forms\Components\Select::make('item_type')
                                    ->label('Item Type')
                                    ->options(ItemType::class)
                                    ->selectablePlaceholder(false)
                                    ->default(ItemType::inventory_product->value)
                                    ->required()
                                    ->reactive()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state === ItemType::inventory_product->value) {
                                            $set('lineItems.*.offering_id', null);
                                        } else {
                                            $set('lineItems.*.product_id', null);
                                        }
                                        $set('quantity', 1);
                                        $set('lineItems.*.description', null);
                                        $set('lineItems.*.unit_price', null);
                                        $set('lineItems.*.salesDiscounts', []);
                                        $set('lineItems.*.salesTaxes', []);
                                    }),
                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('estimate_number')
                                    ->label('Estimate Number')
                                    ->default(fn () => Estimate::getNextDocumentNumber()),
                                Forms\Components\TextInput::make('reference_number')
                                    ->label('Reference Number'),
                                Forms\Components\DatePicker::make('date')
                                    ->label('Estimate Date')
                                    ->live()
                                    ->default(now())
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $date = $state;
                                        $expirationDate = $get('expiration_date');

                                        if ($date && $expirationDate && $date > $expirationDate) {
                                            $set('expiration_date', $date);
                                        }
                                    }),
                                Forms\Components\DatePicker::make('expiration_date')
                                    ->label('Expiration Date')
                                    ->default(function () use ($company) {
                                        return now()->addDays($company->defaultInvoice->payment_terms->getDays());
                                    })
                                    ->minDate(static function (Forms\Get $get) {
                                        return $get('date') ?? now();
                                    }),
                                Forms\Components\Select::make('discount_method')
                                    ->label('Discount Method')
                                    ->options(DocumentDiscountMethod::class)
                                    ->selectablePlaceholder(false)
                                    ->default(DocumentDiscountMethod::PerLineItem)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $discountMethod = DocumentDiscountMethod::parse($state);

                                        if ($discountMethod->isPerDocument()) {
                                            $set('lineItems.*.salesDiscounts', []);
                                        }
                                    })
                                    ->live(),
                            ])->grow(true),
                        ])->from('md'),

                        Forms\Components\Section::make('')
                            ->schema(
                                self::getRepeaterTables() // Directly call the method to include its return value
                            ),
                    ]),
                Forms\Components\Section::make('Estimate Footer')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Textarea::make('footer')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expiration_date')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Expiration Date')
                    ->asRelativeDay()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimate_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.customer_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->currencyWithConversion(static fn (Estimate $record) => $record->currency_code)
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'customer_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(EstimateStatus::class)
                    ->native(false),
                DateRangeFilter::make('date')
                    ->fromLabel('From Date')
                    ->untilLabel('To Date')
                    ->indicatorLabel('Date'),
                DateRangeFilter::make('expiration_date')
                    ->fromLabel('From Expiration Date')
                    ->untilLabel('To Expiration Date')
                    ->indicatorLabel('Due'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Estimate::getReplicateAction(Tables\Actions\ReplicateAction::class),
                    Estimate::getApproveDraftAction(Tables\Actions\Action::class),
                    Estimate::getMarkAsSentAction(Tables\Actions\Action::class),
                    Estimate::getMarkAsAcceptedAction(Tables\Actions\Action::class),
                    Estimate::getMarkAsDeclinedAction(Tables\Actions\Action::class),
                    Estimate::getConvertToInvoiceAction(Tables\Actions\Action::class),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ReplicateBulkAction::make()
                        ->label('Replicate')
                        ->modalWidth(MaxWidth::Large)
                        ->modalDescription('Replicating estimates will also replicate their line items. Are you sure you want to proceed?')
                        ->successNotificationTitle('Estimates Replicated Successfully')
                        ->failureNotificationTitle('Failed to Replicate Estimates')
                        ->databaseTransaction()
                        ->deselectRecordsAfterCompletion()
                        ->excludeAttributes([
                            'estimate_number',
                            'date',
                            'expiration_date',
                            'approved_at',
                            'accepted_at',
                            'converted_at',
                            'declined_at',
                            'last_sent_at',
                            'last_viewed_at',
                            'status',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                        ])
                        ->beforeReplicaSaved(function (Estimate $replica) {
                            $replica->status = EstimateStatus::Draft;
                            $replica->estimate_number = Estimate::getNextDocumentNumber();
                            $replica->date = now();
                            $replica->expiration_date = now()->addDays($replica->company->defaultInvoice->payment_terms->getDays());
                        })
                        ->withReplicatedRelationships(['lineItems'])
                        ->withExcludedRelationshipAttributes('lineItems', [
                            'subtotal',
                            'total',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                        ]),
                    Tables\Actions\BulkAction::make('approveDrafts')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates Approved')
                        ->failureNotificationTitle('Failed to Approve Estimates')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Estimate $record) => ! $record->canBeApproved());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Approval Failed')
                                    ->body('Only draft estimates can be approved. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->approveDraft();
                            });

                            $action->success();
                        }),
                    Tables\Actions\BulkAction::make('markAsSent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates Sent')
                        ->failureNotificationTitle('Failed to Mark Estimates as Sent')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Estimate $record) => ! $record->canBeMarkedAsSent());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Sending Failed')
                                    ->body('Only unsent estimates can be marked as sent. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->markAsSent();
                            });

                            $action->success();
                        }),
                    Tables\Actions\BulkAction::make('markAsAccepted')
                        ->label('Mark as Accepted')
                        ->icon('heroicon-o-check-badge')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates Accepted')
                        ->failureNotificationTitle('Failed to Mark Estimates as Accepted')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Estimate $record) => ! $record->canBeMarkedAsAccepted());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Acceptance Failed')
                                    ->body('Only sent estimates that haven\'t been accepted can be marked as accepted. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->markAsAccepted();
                            });

                            $action->success();
                        }),
                    Tables\Actions\BulkAction::make('markAsDeclined')
                        ->label('Mark as Declined')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->databaseTransaction()
                        ->color('danger')
                        ->modalHeading('Mark Estimates as Declined')
                        ->modalDescription('Are you sure you want to mark the selected estimates as declined? This action cannot be undone.')
                        ->successNotificationTitle('Estimates Declined')
                        ->failureNotificationTitle('Failed to Mark Estimates as Declined')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Estimate $record) => ! $record->canBeMarkedAsDeclined());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Declination Failed')
                                    ->body('Only sent estimates that haven\'t been declined can be marked as declined. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->markAsDeclined();
                            });

                            $action->success();
                        }),
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
            'index' => Pages\ListEstimates::route('/'),
            'create' => Pages\CreateEstimate::route('/create'),
            'view' => Pages\ViewEstimate::route('/{record}'),
            'edit' => Pages\EditEstimate::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            // Widgets\EstimateOverview::class,
        ];
    }

    protected static function getRepeaterTables(): array
    {
        return [
            TableRepeater::make('lineItems')
                ->relationship()
                ->saveRelationshipsUsing(null)
                ->dehydrated(true)
                ->headers(function (Forms\Get $get) {
                    $hasDiscounts = DocumentDiscountMethod::parse($get('discount_method'))->isPerLineItem();

                    $headers = [
                        Header::make('Items')->width($hasDiscounts ? '15%' : '20%'),
                        Header::make('Description')->width($hasDiscounts ? '25%' : '30%'),  // Increase when no discounts
                        Header::make('Quantity')->width('10%'),
                        Header::make('Price')->width('10%'),
                        Header::make('Taxes')->width($hasDiscounts ? '15%' : '20%'),       // Increase when no discounts
                    ];

                    if ($hasDiscounts) {
                        $headers[] = Header::make('Discounts')->width('15%');
                    }

                    $headers[] = Header::make('Amount')->width('10%')->align('right');

                    return $headers;
                })
                ->schema([
                    Forms\Components\Select::make('offering_id')
                        ->label('Item')
                        ->relationship('sellableOffering', 'name')
                        ->preload()
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {

                            $offeringId = $state;
                            $offeringRecord = Offering::with(['salesTaxes', 'salesDiscounts'])->find($offeringId);

                            if ($offeringRecord) {
                                $set('description', $offeringRecord->description);
                                $set('unit_price', $offeringRecord->price);
                                $set('salesTaxes', $offeringRecord->salesTaxes->pluck('id')->toArray());

                                $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));
                                if ($discountMethod->isPerLineItem()) {
                                    $set('salesDiscounts', $offeringRecord->salesDiscounts->pluck('id')->toArray());
                                }
                            }
                        }),
                    Forms\Components\TextInput::make('description'),
                    Forms\Components\TextInput::make('quantity')
                        ->required()
                        ->numeric()
                        ->live()
                        ->default(1),
                    Forms\Components\TextInput::make('unit_price')
                        ->label('Price')
                        ->hiddenLabel()
                        ->numeric()
                        ->live()
                        ->default(0),
                    Forms\Components\Select::make('salesTaxes')
                        ->label('Taxes')
                        ->relationship('salesTaxes', 'name')
                        ->saveRelationshipsUsing(null)
                        ->dehydrated(true)
                        ->preload()
                        ->multiple()
                        ->live()
                        ->searchable(),
                    Forms\Components\Select::make('salesDiscounts')
                        ->label('Discounts')
                        ->relationship('salesDiscounts', 'name')
                        ->saveRelationshipsUsing(null)
                        ->dehydrated(true)
                        ->preload()
                        ->multiple()
                        ->live()
                        ->hidden(function (Forms\Get $get) {
                            $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));

                            return $discountMethod->isPerDocument();
                        })
                        ->searchable(),
                    Forms\Components\Placeholder::make('total')
                        ->hiddenLabel()
                        ->extraAttributes(['class' => 'text-left sm:text-right'])
                        ->content(function (Forms\Get $get) {
                            $quantity = max((float) ($get('quantity') ?? 0), 0);
                            $unitPrice = max((float) ($get('unit_price') ?? 0), 0);
                            $salesTaxes = $get('salesTaxes') ?? [];
                            $salesDiscounts = $get('salesDiscounts') ?? [];
                            $currencyCode = $get('../../currency_code') ?? CurrencyAccessor::getDefaultCurrency();

                            $subtotal = $quantity * $unitPrice;

                            $subtotalInCents = CurrencyConverter::convertToCents($subtotal, $currencyCode);

                            $taxAmountInCents = Adjustment::whereIn('id', $salesTaxes)
                                ->get()
                                ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                    if ($adjustment->computation->isPercentage()) {
                                        return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                    } else {
                                        return $adjustment->getRawOriginal('rate');
                                    }
                                });

                            $discountAmountInCents = Adjustment::whereIn('id', $salesDiscounts)
                                ->get()
                                ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                    if ($adjustment->computation->isPercentage()) {
                                        return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                    } else {
                                        return $adjustment->getRawOriginal('rate');
                                    }
                                });

                            // Final total
                            $totalInCents = $subtotalInCents + ($taxAmountInCents - $discountAmountInCents);

                            return CurrencyConverter::formatCentsToMoney($totalInCents, $currencyCode);
                        }),
                ])->visible(function ($get) {
                    $itemType = $get('item_type');

                    return $itemType === ItemType::offering->value;
                }),

            TableRepeater::make('lineItems')
                ->relationship()
                ->saveRelationshipsUsing(null)
                ->dehydrated(true)
                ->headers(function (Forms\Get $get) {
                    $hasDiscounts = DocumentDiscountMethod::parse($get('discount_method'))->isPerLineItem();

                    $headers = [
                        Header::make('Items')->width($hasDiscounts ? '15%' : '20%'),
                        Header::make('Description')->width($hasDiscounts ? '25%' : '30%'),  // Increase when no discounts
                        Header::make('Quantity')->width('10%'),
                        Header::make('Price')->width('10%'),
                        Header::make('Taxes')->width($hasDiscounts ? '15%' : '20%'),       // Increase when no discounts
                    ];

                    if ($hasDiscounts) {
                        $headers[] = Header::make('Discounts')->width('15%');
                    }

                    $headers[] = Header::make('Amount')->width('10%')->align('right');

                    return $headers;
                })
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Product')
                        ->relationship('purchasableProducts', 'product_name')
                        ->preload()
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {

                            $offeringId = $state;
                            $offeringRecord = Product::with(['salesTaxes', 'salesDiscounts'])->find($offeringId);

                            if ($offeringRecord) {
                                $set('description', $offeringRecord->product_note);
                                $set('unit_price', $offeringRecord->product_price);
                                $set('salesTaxes', $offeringRecord->salesTaxes->pluck('id')->toArray());

                                $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));
                                if ($discountMethod->isPerLineItem()) {
                                    $set('salesDiscounts', $offeringRecord->salesDiscounts->pluck('id')->toArray());
                                }
                            }
                        }),
                    Forms\Components\TextInput::make('description'),
                    Forms\Components\TextInput::make('quantity')
                        ->required()
                        ->numeric()
                        ->live()
                        ->default(1),
                    Forms\Components\TextInput::make('unit_price')
                        ->label('Price')
                        ->hiddenLabel()
                        ->numeric()
                        ->live()
                        ->default(0),
                    Forms\Components\Select::make('salesTaxes')
                        ->label('Taxes')
                        ->relationship('salesTaxes', 'name')
                        ->saveRelationshipsUsing(null)
                        ->dehydrated(true)
                        ->preload()
                        ->multiple()
                        ->live()
                        ->searchable(),
                    Forms\Components\Select::make('salesDiscounts')
                        ->label('Discounts')
                        ->relationship('salesDiscounts', 'name')
                        ->saveRelationshipsUsing(null)
                        ->dehydrated(true)
                        ->preload()
                        ->multiple()
                        ->live()
                        ->hidden(function (Forms\Get $get) {
                            $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));

                            return $discountMethod->isPerDocument();
                        })
                        ->searchable(),
                    Forms\Components\Placeholder::make('total')
                        ->hiddenLabel()
                        ->extraAttributes(['class' => 'text-left sm:text-right'])
                        ->content(function (Forms\Get $get) {
                            $quantity = max((float) ($get('quantity') ?? 0), 0);
                            $unitPrice = max((float) ($get('unit_price') ?? 0), 0);
                            $salesTaxes = $get('salesTaxes') ?? [];
                            $salesDiscounts = $get('salesDiscounts') ?? [];
                            $currencyCode = $get('../../currency_code') ?? CurrencyAccessor::getDefaultCurrency();

                            $subtotal = $quantity * $unitPrice;

                            $subtotalInCents = CurrencyConverter::convertToCents($subtotal, $currencyCode);

                            $taxAmountInCents = Adjustment::whereIn('id', $salesTaxes)
                                ->get()
                                ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                    if ($adjustment->computation->isPercentage()) {
                                        return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                    } else {
                                        return $adjustment->getRawOriginal('rate');
                                    }
                                });

                            $discountAmountInCents = Adjustment::whereIn('id', $salesDiscounts)
                                ->get()
                                ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                    if ($adjustment->computation->isPercentage()) {
                                        return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                    } else {
                                        return $adjustment->getRawOriginal('rate');
                                    }
                                });

                            // Final total
                            $totalInCents = $subtotalInCents + ($taxAmountInCents - $discountAmountInCents);

                            return CurrencyConverter::formatCentsToMoney($totalInCents, $currencyCode);
                        }),
                ])->visible(function ($get) {
                    $itemType = $get('item_type');

                    return $itemType === ItemType::inventory_product->value;
                }),
            DocumentTotals::make()
                ->type(DocumentType::Estimate),
        ];
    }
}
