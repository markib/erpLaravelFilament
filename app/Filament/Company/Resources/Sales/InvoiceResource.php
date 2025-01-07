<?php

namespace App\Filament\Company\Resources\Sales;

use App\Collections\Accounting\InvoiceCollection;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\PaymentMethod;
use App\Enums\Common\ItemType;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages;
use App\Filament\Company\Resources\Sales\InvoiceResource\RelationManagers;
use App\Filament\Company\Resources\Sales\InvoiceResource\Widgets;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\DocumentTotals;
use App\Filament\Tables\Actions\ReplicateBulkAction;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Invoice;
use App\Models\Banking\BankAccount;
use App\Models\Common\Offering;
use App\Models\Parties\Customer;
use App\Models\Product\Product;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Carbon\Carbon;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    // protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Header')
                    ->collapsible()
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
                                    ->default(fn () => $company->defaultInvoice->header),
                                Forms\Components\TextInput::make('subheader')
                                    ->default(fn () => $company->defaultInvoice->subheader),
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
                Forms\Components\Section::make('Invoice Details')
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
                                CreateCurrencySelect::make('currency_code'), Forms\Components\Placeholder::make('')->extraAttributes(['class' => 'h-32 mt-8']),

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
                                Forms\Components\TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->default(fn () => Invoice::getNextDocumentNumber()),
                                Forms\Components\TextInput::make('order_number')
                                    ->label('P.O/S.O Number'),
                                Forms\Components\DatePicker::make('date')
                                    ->label('Invoice Date')
                                    ->live()
                                    ->default(now())
                                    ->disabled(function (?Invoice $record) {
                                        return $record?->hasPayments();
                                    })
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $date = $state;
                                        $dueDate = $get('due_date');

                                        if ($date && $dueDate && $date > $dueDate) {
                                            $set('due_date', $date);
                                        }
                                    }),
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Payment Due')
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

                        Forms\Components\Textarea::make('terms')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Invoice Footer')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Textarea::make('footer')
                            ->columnSpanFull(),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->formatStateUsing(fn (string $state): string => Carbon::parse($state)->diffForHumans())
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.customer_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                     // ->currency()
                    ->currencyWithConversion(static fn (Invoice $record) => $record->currency_code)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->currencyWithConversion(static fn (Invoice $record) => $record->currency_code)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->currencyWithConversion(static fn (Invoice $record) => $record->currency_code)
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'customer_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(InvoiceStatus::class)
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
                    Invoice::getReplicateAction(Tables\Actions\ReplicateAction::class),
                    Invoice::getApproveDraftAction(Tables\Actions\Action::class),
                    Invoice::getMarkAsSentAction(Tables\Actions\Action::class),
                    Tables\Actions\Action::make('recordPayment')
                        ->label(fn (Invoice $record) => $record->status === InvoiceStatus::Overpaid ? 'Refund Overpayment' : 'Record Payment')
                        ->stickyModalHeader()
                        ->stickyModalFooter()
                        ->modalFooterActionsAlignment(Alignment::End)
                        ->modalWidth(MaxWidth::TwoExtraLarge)
                        ->icon('heroicon-o-credit-card')
                        ->visible(function (Invoice $record) {
                            return $record->canRecordPayment();
                        })
                        ->mountUsing(function (Invoice $record, Form $form) {
                            $form->fill([
                                'posted_at' => now(),
                                'amount' => $record->status === InvoiceStatus::Overpaid ? ltrim($record->amount_due, '-') : $record->amount_due,
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
                                ->money()
                                ->live(onBlur: true)
                                ->helperText(function (Invoice $record, $state) {
                                    if (! CurrencyConverter::isValidAmount($state)) {
                                        return null;
                                    }

                                    $amountDue = $record->getRawOriginal('amount_due');

                                    $amount = CurrencyConverter::convertToCents($state);

                                    if ($amount <= 0) {
                                        return 'Please enter a valid positive amount';
                                    }

                                    if ($record->status === InvoiceStatus::Overpaid) {
                                        $newAmountDue = $amountDue + $amount;
                                    } else {
                                        $newAmountDue = $amountDue - $amount;
                                    }

                                    return match (true) {
                                        $newAmountDue > 0 => 'Amount due after payment will be ' . CurrencyConverter::formatCentsToMoney($newAmountDue),
                                        $newAmountDue === 0 => 'Invoice will be fully paid',
                                        default => 'Invoice will be overpaid by ' . CurrencyConverter::formatCentsToMoney(abs($newAmountDue)),
                                    };
                                })
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
                        ->action(function (Invoice $record, Tables\Actions\Action $action, array $data) {
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
                        ->modalDescription('Replicating invoices will also replicate their line items. Are you sure you want to proceed?')
                        ->successNotificationTitle('Invoices Replicated Successfully')
                        ->failureNotificationTitle('Failed to Replicate Invoices')
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
                            'invoice_number',
                            'date',
                            'due_date',
                        ])
                        ->beforeReplicaSaved(function (Invoice $replica) {
                            $replica->status = InvoiceStatus::Draft;
                            $replica->invoice_number = Invoice::getNextDocumentNumber();
                            $replica->date = now();
                            $replica->due_date = now()->addDays($replica->company->defaultInvoice->payment_terms->getDays());
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
                        ->successNotificationTitle('Invoices Approved')
                        ->failureNotificationTitle('Failed to Approve Invoices')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $containsNonDrafts = $records->contains(fn (Invoice $record) => ! $record->isDraft());

                            if ($containsNonDrafts) {
                                Notification::make()
                                    ->title('Approval Failed')
                                    ->body('Only draft invoices can be approved. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Invoice $record) {
                                $record->approveDraft();
                            });

                            $action->success();
                        }),
                    Tables\Actions\BulkAction::make('markAsSent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->databaseTransaction()
                        ->successNotificationTitle('Invoices Sent')
                        ->failureNotificationTitle('Failed to Mark Invoices as Sent')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $doesntContainUnsent = $records->contains(fn (Invoice $record) => $record->status !== InvoiceStatus::Unsent);

                            if ($doesntContainUnsent) {
                                Notification::make()
                                    ->title('Sending Failed')
                                    ->body('Only unsent invoices can be marked as sent. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Invoice $record) {
                                $record->updateQuietly([
                                    'status' => InvoiceStatus::Sent,
                                ]);
                            });

                            $action->success();
                        }),
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
                            $cantRecordPayments = $records->contains(fn (Invoice $record) => ! $record->canBulkRecordPayment());

                            if ($cantRecordPayments) {
                                Notification::make()
                                    ->title('Payment Recording Failed')
                                    ->body('Invoices that are either draft, paid, overpaid, or voided cannot be processed through bulk payments. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->mountUsing(function (InvoiceCollection $records, Form $form) {
                            $totalAmountDue = $records->sumMoneyFormattedSimple('amount_due');

                            $form->fill([
                                'posted_at' => now(),
                                'amount' => $totalAmountDue,
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
                        ->before(function (InvoiceCollection $records, Tables\Actions\BulkAction $action, array $data) {
                            $totalPaymentAmount = CurrencyConverter::convertToCents($data['amount']);
                            $totalAmountDue = $records->sumMoneyInCents('amount_due');

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
                        ->action(function (InvoiceCollection $records, Tables\Actions\BulkAction $action, array $data) {
                            $totalPaymentAmount = CurrencyConverter::convertToCents($data['amount']);

                            $remainingAmount = $totalPaymentAmount;

                            $records->each(function (Invoice $record) use (&$remainingAmount, $data) {
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
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\InvoiceOverview::class,
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
                        ->hiddenLabel()
                        ->numeric()
                        ->live()
                        ->default(0),
                    Forms\Components\Select::make('salesTaxes')
                        ->relationship('salesTaxes', 'name')
                        ->saveRelationshipsUsing(null)
                        ->dehydrated(true)
                        ->preload()
                        ->multiple()
                        ->live()
                        ->searchable(),

                    Forms\Components\Select::make('salesDiscounts')
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
                            // $total = $subtotal + ($taxAmount - $discountAmount);
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
                        ->hiddenLabel()
                        ->numeric()
                        ->live()
                        ->default(0),
                    Forms\Components\Select::make('salesTaxes')
                        ->relationship('salesTaxes', 'name')
                        ->saveRelationshipsUsing(null)
                        ->dehydrated(true)
                        ->preload()
                        ->multiple()
                        ->live()
                        ->searchable(),

                    Forms\Components\Select::make('salesDiscounts')
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
                ->type(DocumentType::Invoice),
        ];

    }
}
