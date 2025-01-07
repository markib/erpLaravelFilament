<?php

namespace App\Filament\Company\Resources\Purchases;

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\PaymentMethod;
use App\Enums\Common\ItemType;
use App\Filament\Company\Resources\Purchases\BillResource\Pages;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\DocumentTotals;
use App\Filament\Tables\Actions\ReplicateBulkAction;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Bill;
use App\Models\Banking\BankAccount;
use App\Models\Common\Offering;
use App\Models\Parties\Supplier;
use App\Models\Product\Product;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;

    // protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        return $form
            ->schema([
                Forms\Components\Section::make('Bill Details')
                    ->schema([
                        Forms\Components\Split::make([
                            Forms\Components\Group::make([
                                Forms\Components\Select::make('vendor_id')
                                    ->relationship('vendor', 'supplier_name')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $currencyCode = Supplier::find($state)?->currency_code;

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
                                        $set('lineItems.*.purchaseDiscounts', []);
                                        $set('lineItems.*.purchaseTaxes', []);
                                    }),
                            ]),

                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('bill_number')
                                    ->label('Bill Number')
                                    ->default(fn () => Bill::getNextDocumentNumber())
                                    ->required(),
                                Forms\Components\TextInput::make('order_number')
                                    ->label('P.O/S.O Number'),
                                Forms\Components\DatePicker::make('date')
                                    ->label('Bill Date')
                                    ->default(now())
                                    ->disabled(function (?Bill $record) {
                                        return $record?->hasPayments();
                                    })
                                    ->required(),
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->default(function () use ($company) {
                                        return now()->addDays($company->defaultBill->payment_terms->getDays());
                                    })
                                    ->required(),
                                Forms\Components\Select::make('discount_method')
                                    ->label('Discount Method')
                                    ->options(DocumentDiscountMethod::class)
                                    ->selectablePlaceholder(false)
                                    ->default(DocumentDiscountMethod::PerLineItem)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $discountMethod = DocumentDiscountMethod::parse($state);

                                        if ($discountMethod->isPerDocument()) {
                                            $set('lineItems.*.purchaseDiscounts', []);
                                            $set('lineItems.*.quantity', 1);
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->asRelativeDay()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bill_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor.supplier_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->currencyWithConversion(static fn (Bill $record) => $record->currency_code)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->currencyWithConversion(static fn (Bill $record) => $record->currency_code)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->currencyWithConversion(static fn (Bill $record) => $record->currency_code)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vendor')
                    ->relationship('vendor', 'supplier_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(BillStatus::class)
                    ->native(false),
                Tables\Filters\TernaryFilter::make('has_payments')
                    ->label('Has Payments')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('payments'),
                        false: fn (Builder $query) => $query->whereDoesntHave('payments'),
                    ),
                DateRangeFilter::make('date')
                    ->fromLabel('From Date')
                    ->untilLabel('To Date')
                    ->indicatorLabel('Date'),
                DateRangeFilter::make('due_date')
                    ->fromLabel('From Due Date')
                    ->untilLabel('To Due Date')
                    ->indicatorLabel('Due'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Bill::getReplicateAction(Tables\Actions\ReplicateAction::class),
                    Tables\Actions\Action::make('recordPayment')
                        ->label('Record Payment')
                        ->stickyModalHeader()
                        ->stickyModalFooter()
                        ->modalFooterActionsAlignment(Alignment::End)
                        ->modalWidth(MaxWidth::TwoExtraLarge)
                        ->icon('heroicon-o-credit-card')
                        ->visible(function (Bill $record) {
                            return $record->canRecordPayment();
                        })
                        ->mountUsing(function (Bill $record, Form $form) {
                            $form->fill([
                                'posted_at' => now(),
                                'amount' => $record->amount_due,
                            ]);
                        })
                        ->databaseTransaction()
                        ->successNotificationTitle('Payment Recorded')
                        ->form([
                            Forms\Components\DatePicker::make('posted_at')
                                ->label('Date'),
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount')
                                ->required()
                                ->money(fn (Bill $record) => $record->currency_code)
                                ->live(onBlur: true)
                                ->helperText(function (Bill $record, $state) {
                                    $billCurrency = $record->currency_code;
                                    if (! CurrencyConverter::isValidAmount($state, $billCurrency)) {
                                        return null;
                                    }

                                    $amountDue = $record->getRawOriginal('amount_due');
                                    $amount = CurrencyConverter::convertToCents($state, $billCurrency);

                                    if ($amount <= 0) {
                                        return 'Please enter a valid positive amount';
                                    }

                                    $newAmountDue = $amountDue - $amount;

                                    return match (true) {
                                        $newAmountDue > 0 => 'Amount due after payment will be ' . CurrencyConverter::formatCentsToMoney($newAmountDue, $billCurrency),
                                        $newAmountDue === 0 => 'Bill will be fully paid',
                                        default => 'Amount exceeds bill total by ' . CurrencyConverter::formatCentsToMoney(abs($newAmountDue), $billCurrency),
                                    };
                                })
                                ->rules([
                                    static fn (Bill $record): Closure => static function (string $attribute, $value, Closure $fail) use ($record) {
                                        if (! CurrencyConverter::isValidAmount($value, $record->currency_code)) {
                                            $fail('Please enter a valid amount');
                                        }
                                    },
                                ]),
                            Forms\Components\Select::make('payment_method')
                                ->label('Payment Method')
                                ->required()
                                ->options(PaymentMethod::class),
                            Forms\Components\Select::make('bank_account_id')
                                ->label('Account')
                                ->required()
                                ->options(BankAccount::query()
                                    ->get()
                                    ->pluck('account.name', 'id'))
                                ->searchable(),
                            Forms\Components\Textarea::make('notes')
                                ->label('Notes'),
                        ])
                        ->action(function (Bill $record, Tables\Actions\Action $action, array $data) {
                            $record->recordPayment($data);

                            $action->success();
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ReplicateBulkAction::make()
                        ->label('Replicate')
                        ->modalWidth(MaxWidth::Large)
                        ->modalDescription('Replicating bills will also replicate their line items. Are you sure you want to proceed?')
                        ->successNotificationTitle('Bills Replicated Successfully')
                        ->failureNotificationTitle('Failed to Replicate Bills')
                        ->databaseTransaction()
                        ->deselectRecordsAfterCompletion()
                        ->excludeAttributes([
                            'status',
                            'amount_paid',
                            'amount_due',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                            'bill_number',
                            'date',
                            'due_date',
                            'paid_at',
                        ])
                        ->beforeReplicaSaved(function (Bill $replica) {
                            $replica->status = BillStatus::Unpaid;
                            $replica->bill_number = Bill::getNextDocumentNumber();
                            $replica->date = now();
                            $replica->due_date = now()->addDays($replica->company->defaultBill->payment_terms->getDays());
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
                    Tables\Actions\BulkAction::make('recordPayments')
                        ->label('Record Payments')
                        ->icon('heroicon-o-credit-card')
                        ->stickyModalHeader()
                        ->stickyModalFooter()
                        ->modalFooterActionsAlignment(Alignment::End)
                        ->modalWidth(MaxWidth::TwoExtraLarge)
                        ->databaseTransaction()
                        ->successNotificationTitle('Payments Recorded')
                        ->failureNotificationTitle('Failed to Record Payments')
                        ->deselectRecordsAfterCompletion()
                        ->beforeFormFilled(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Bill $bill) => ! $bill->canRecordPayment());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Payment Recording Failed')
                                    ->body('Bills that are either paid, voided, or are in a foreign currency cannot be processed through bulk payments. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->mountUsing(function (Collection $records, Form $form) {
                            $totalAmountDue = $records->sum(fn (Bill $bill) => $bill->getRawOriginal('amount_due'));

                            $form->fill([
                                'posted_at' => now(),
                                'amount' => CurrencyConverter::convertCentsToFormatSimple($totalAmountDue),
                            ]);
                        })
                        ->form([
                            Forms\Components\DatePicker::make('posted_at')
                                ->label('Date'),
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount')
                                ->required()
                                ->money()
                                ->rules([
                                    static fn (): Closure => static function (string $attribute, $value, Closure $fail) {
                                        if (! CurrencyConverter::isValidAmount($value)) {
                                            $fail('Please enter a valid amount');
                                        }
                                    },
                                ]),
                            Forms\Components\Select::make('payment_method')
                                ->label('Payment Method')
                                ->required()
                                ->options(PaymentMethod::class),
                            Forms\Components\Select::make('bank_account_id')
                                ->label('Account')
                                ->required()
                                ->options(BankAccount::query()
                                    ->get()
                                    ->pluck('account.name', 'id'))
                                ->searchable(),
                            Forms\Components\Textarea::make('notes')
                                ->label('Notes'),
                        ])
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action, array $data) {
                            $totalPaymentAmount = CurrencyConverter::convertToCents($data['amount']);
                            $totalAmountDue = $records->sum(fn (Bill $bill) => $bill->getRawOriginal('amount_due'));

                            if ($totalPaymentAmount > $totalAmountDue) {
                                $formattedTotalAmountDue = CurrencyConverter::formatCentsToMoney($totalAmountDue);

                                Notification::make()
                                    ->title('Excess Payment Amount')
                                    ->body("The payment amount exceeds the total amount due of {$formattedTotalAmountDue}. Please adjust the payment amount and try again.")
                                    ->persistent()
                                    ->warning()
                                    ->send();

                                $action->halt(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action, array $data) {
                            $totalPaymentAmount = CurrencyConverter::convertToCents($data['amount']);
                            $remainingAmount = $totalPaymentAmount;

                            $records->each(function (Bill $record) use (&$remainingAmount, $data) {
                                $amountDue = $record->getRawOriginal('amount_due');

                                if ($amountDue <= 0 || $remainingAmount <= 0) {
                                    return;
                                }

                                $paymentAmount = min($amountDue, $remainingAmount);
                                $data['amount'] = CurrencyConverter::convertCentsToFormatSimple($paymentAmount);

                                $record->recordPayment($data);
                                $remainingAmount -= $paymentAmount;
                            });

                            $action->success();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BillResource\RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBills::route('/'),
            'create' => Pages\CreateBill::route('/create'),
            'view' => Pages\ViewBill::route('/{record}'),
            'edit' => Pages\EditBill::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            BillResource\Widgets\BillOverview::class,
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
                        ->relationship('purchasableOffering', 'name')
                        ->preload()
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                            logger()->info($state);
                            $offeringId = $state;
                            $offeringRecord = Offering::with(['purchaseTaxes', 'purchaseDiscounts'])->find($offeringId);

                            if ($offeringRecord) {
                                $set('description', $offeringRecord->description);
                                $set('unit_price', $offeringRecord->price);
                                $set('purchaseTaxes', $offeringRecord->purchaseTaxes->pluck('id')->toArray());

                                $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));
                                if ($discountMethod->isPerLineItem()) {
                                    $set('purchaseDiscounts', $offeringRecord->purchaseDiscounts->pluck('id')->toArray());
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
                    Forms\Components\Select::make('purchaseTaxes')
                        ->label('Taxes')
                        ->relationship('purchaseTaxes', 'name')
                        ->saveRelationshipsUsing(null)
                        ->dehydrated(true)
                        ->preload()
                        ->multiple()
                        ->live()
                        ->searchable(),
                    Forms\Components\Select::make('purchaseDiscounts')
                        ->label('Discounts')
                        ->relationship('purchaseDiscounts', 'name')
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
                            $purchaseTaxes = $get('purchaseTaxes') ?? [];
                            $purchaseDiscounts = $get('purchaseDiscounts') ?? [];
                            $currencyCode = $get('../../currency_code') ?? CurrencyAccessor::getDefaultCurrency();

                            $subtotal = $quantity * $unitPrice;

                            $subtotalInCents = CurrencyConverter::convertToCents($subtotal, $currencyCode);

                            $taxAmountInCents = Adjustment::whereIn('id', $purchaseTaxes)
                                ->get()
                                ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                    if ($adjustment->computation->isPercentage()) {
                                        return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                    } else {
                                        return $adjustment->getRawOriginal('rate');
                                    }
                                });

                            $discountAmountInCents = Adjustment::whereIn('id', $purchaseDiscounts)
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
                            logger()->warning($state);
                            $offeringId = $state;
                            $offeringRecord = Product::with(['purchaseTaxes', 'purchaseDiscounts'])->find($offeringId);

                            if ($offeringRecord) {
                                $set('description', $offeringRecord->product_note);
                                $set('unit_price', $offeringRecord->product_price);
                                $set('purchaseTaxes', $offeringRecord->purchaseTaxes->pluck('id')->toArray());

                                $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));
                                if ($discountMethod->isPerLineItem()) {
                                    $set('purchaseDiscounts', $offeringRecord->purchaseDiscounts->pluck('id')->toArray());
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
                    Forms\Components\Select::make('purchaseTaxes')
                        ->label('Taxes')
                        ->relationship('purchaseTaxes', 'name')
                        ->saveRelationshipsUsing(null)
                        ->dehydrated(true)
                        ->preload()
                        ->multiple()
                        ->live()
                        ->searchable(),
                    Forms\Components\Select::make('purchaseDiscounts')
                        ->label('Discounts')
                        ->relationship('purchaseDiscounts', 'name')
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
                            $purchaseTaxes = $get('purchaseTaxes') ?? [];
                            $purchaseDiscounts = $get('purchaseDiscounts') ?? [];
                            $currencyCode = $get('../../currency_code') ?? CurrencyAccessor::getDefaultCurrency();

                            $subtotal = $quantity * $unitPrice;

                            $subtotalInCents = CurrencyConverter::convertToCents($subtotal, $currencyCode);

                            $taxAmountInCents = Adjustment::whereIn('id', $purchaseTaxes)
                                ->get()
                                ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                    if ($adjustment->computation->isPercentage()) {
                                        return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                    } else {
                                        return $adjustment->getRawOriginal('rate');
                                    }
                                });

                            $discountAmountInCents = Adjustment::whereIn('id', $purchaseDiscounts)
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
                ->type(DocumentType::Bill),
        ];
    }
}
