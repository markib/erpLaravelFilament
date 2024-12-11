<?php

namespace App\Filament\Company\Resources\Sales;

use App\Collections\Accounting\InvoiceCollection;
use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\PaymentMethod;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages;
use App\Filament\Company\Resources\Sales\InvoiceResource\RelationManagers;
use App\Filament\Tables\Actions\ReplicateBulkAction;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Common\Offering;
use App\Utilities\Currency\CurrencyConverter;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Banking\BankAccount;
use Filament\Support\Enums\Alignment;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use App\Filament\Company\Resources\Sales\InvoiceResource\Widgets;
use Carbon\Carbon;




class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    // protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;
       
        return $form
            ->schema([Forms\Components\Section::make('Invoice Header')
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
                                    static fn(TemporaryUploadedFile $file): string => (string) str($file->getClientOriginalName())
                                        ->prepend(Auth::user()->currentCompany->id . '_'),
                                )
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif']),
                        ]),
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('header')
                                ->default(fn() => $company->defaultInvoice->header),
                            Forms\Components\TextInput::make('subheader')
                                ->default(fn() => $company->defaultInvoice->subheader),
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
                                    ->required(),
                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->default(fn() => Invoice::getNextDocumentNumber()),
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
                            ])->grow(true),
                        ])->from('md'),
                        TableRepeater::make('lineItems')
                            ->relationship()
                            ->saveRelationshipsUsing(function (TableRepeater $component, Forms\Contracts\HasForms $livewire, ?array $state) {
                                if (! is_array($state)) {
                                    $state = [];
                                }

                                $relationship = $component->getRelationship();
                                 
                                $existingRecords = $component->getCachedExistingRecords();

                                $recordsToDelete = [];

                                foreach ($existingRecords->pluck($relationship->getRelated()->getKeyName()) as $keyToCheckForDeletion) {
                                    if (array_key_exists("record-{$keyToCheckForDeletion}", $state)) {
                                        continue;
                                    }

                                    $recordsToDelete[] = $keyToCheckForDeletion;
                                    $existingRecords->forget("record-{$keyToCheckForDeletion}");
                                }

                                $relationship
                                    ->whereKey($recordsToDelete)
                                    ->get()
                                    ->each(static fn(Model $record) => $record->delete());

                                $childComponentContainers = $component->getChildComponentContainers(
                                    withHidden: $component->shouldSaveRelationshipsWhenHidden(),
                                );

                                $itemOrder = 1;
                                $orderColumn = $component->getOrderColumn();

                                $translatableContentDriver = $livewire->makeFilamentTranslatableContentDriver();

                                foreach ($childComponentContainers as $itemKey => $item) {
                                    $itemData = $item->getState(shouldCallHooksBefore: false);

                                    if ($orderColumn) {
                                        $itemData[$orderColumn] = $itemOrder;

                                        $itemOrder++;
                                    }

                                    if ($record = ($existingRecords[$itemKey] ?? null)) {
                                        $itemData = $component->mutateRelationshipDataBeforeSave($itemData, record: $record);

                                        if ($itemData === null) {
                                            continue;
                                        }

                                        $translatableContentDriver ?
                                            $translatableContentDriver->updateRecord($record, $itemData) :
                                            $record->fill($itemData)->save();

                                        continue;
                                    }

                                    $relatedModel = $component->getRelatedModel();

                                    $itemData = $component->mutateRelationshipDataBeforeCreate($itemData);

                                    if ($itemData === null) {
                                        continue;
                                    }

                                    if ($translatableContentDriver) {
                                        $record = $translatableContentDriver->makeRecord($relatedModel, $itemData);
                                    } else {
                                        $record = new $relatedModel;
                                        $record->fill($itemData);
                                    }

                                    $record = $relationship->save($record);
                                    $item->model($record)->saveRelationships();
                                    $existingRecords->push($record);
                                }

                                $component->getRecord()->setRelation($component->getRelationshipName(), $existingRecords);

                                /** @var Invoice $invoice */
                                $invoice = $component->getRecord();

                                // Recalculate totals for line items
                                $invoice->lineItems()->each(function (DocumentLineItem $lineItem) {
                                    $lineItem->updateQuietly([
                                        'tax_total' => $lineItem->calculateTaxTotal()->getAmount(),
                                        'discount_total' => $lineItem->calculateDiscountTotal()->getAmount(),
                                    ]);
                                });

                                $subtotal = $invoice->lineItems()->sum('subtotal') / 100;
                                $taxTotal = $invoice->lineItems()->sum('tax_total') / 100;
                                $discountTotal = $invoice->lineItems()->sum('discount_total') / 100;
                                $grandTotal = $subtotal + $taxTotal - $discountTotal;

                                $invoice->updateQuietly([
                                    'subtotal' => $subtotal,
                                    'tax_total' => $taxTotal,
                                    'discount_total' => $discountTotal,
                                    'total' => $grandTotal,
                                ]);

                                if ($invoice->approved_at && $invoice->approvalTransaction) {
                                    $invoice->updateApprovalTransaction();
                                }
                            })
                            ->headers([
                                Header::make('Items')->width('15%'),
                                Header::make('Description')->width('25%'),
                                Header::make('Quantity')->width('10%'),
                                Header::make('Price')->width('10%'),
                                Header::make('Taxes')->width('15%'),
                                Header::make('Discounts')->width('15%'),
                                Header::make('Amount')->width('10%')->align('right'),
                            ])
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
                                            $set('salesDiscounts', $offeringRecord->salesDiscounts->pluck('id')->toArray());
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
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable(),
                                Forms\Components\Select::make('salesDiscounts')
                                    ->relationship('salesDiscounts', 'name')
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable(),
                                Forms\Components\Placeholder::make('total')
                                    ->hiddenLabel()
                                    ->content(function (Forms\Get $get) {
                                        $quantity = max((float) ($get('quantity') ?? 0), 0);
                                        $unitPrice = max((float) ($get('unit_price') ?? 0), 0);
                                        $salesTaxes = $get('salesTaxes') ?? [];
                                        $salesDiscounts = $get('salesDiscounts') ?? [];

                                        $subtotal = $quantity * $unitPrice;

                                        // Calculate tax amount based on subtotal
                                        $taxAmount = 0;
                                        if (! empty($salesTaxes)) {
                                            $taxRates = Adjustment::whereIn('id', $salesTaxes)->pluck('rate');
                                            $taxAmount = collect($taxRates)->sum(fn($rate) => $subtotal * ($rate / 100));
                                        }

                                        // Calculate discount amount based on subtotal
                                        $discountAmount = 0;
                                        if (! empty($salesDiscounts)) {
                                            $discountRates = Adjustment::whereIn('id', $salesDiscounts)->pluck('rate');
                                            $discountAmount = collect($discountRates)->sum(fn($rate) => $subtotal * ($rate / 100));
                                        }

                                        // Final total
                                        $total = $subtotal + ($taxAmount - $discountAmount);

                                        return CurrencyConverter::formatToMoney($total);
                                    }),
                            ]),
                        Forms\Components\Grid::make(6)
                            ->schema([
                                Forms\Components\ViewField::make('totals')
                                    ->columnStart(5)
                                    ->columnSpan(2)
                                    ->view('filament.forms.components.invoice-totals'),
                            ]),
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
                    ->formatStateUsing(fn(string $state): string => Carbon::parse($state)->diffForHumans())
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
                    ->currency()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->currency()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->currency()
                    ->sortable(),
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
                        true: fn(Builder $query) => $query->whereHas('payments'),
                        false: fn(Builder $query) => $query->whereDoesntHave('payments'),
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
                        ->label(fn(Invoice $record) => $record->status === InvoiceStatus::Overpaid ? 'Refund Overpayment' : 'Record Payment')
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
                                    static fn(): Closure => static function (string $attribute, $value, Closure $fail) {
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
                            $containsNonDrafts = $records->contains(fn(Invoice $record) => ! $record->isDraft());

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
                            $doesntContainUnsent = $records->contains(fn(Invoice $record) => $record->status !== InvoiceStatus::Unsent);

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
                            $cantRecordPayments = $records->contains(fn(Invoice $record) => ! $record->canBulkRecordPayment());

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
                                    static fn(): Closure => static function (string $attribute, $value, Closure $fail) {
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
            // RelationManagers\PaymentsRelationManager::class,
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
}