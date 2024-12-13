<?php

namespace App\Filament\Company\Resources\Purchases;

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\PaymentMethod;
use App\Filament\Company\Resources\Purchases\BillResource\Pages;
use App\Filament\Tables\Actions\ReplicateBulkAction;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Banking\BankAccount;
use App\Models\Common\Offering;
use App\Utilities\Currency\CurrencyConverter;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Carbon\Carbon;
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
use Illuminate\Database\Eloquent\Model;
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
                                    ->required(),
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
                                    ->each(static fn (Model $record) => $record->delete());

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

                                /** @var Bill $bill */
                                $bill = $component->getRecord();

                                // Recalculate totals for line items
                                $bill->lineItems()->each(function (DocumentLineItem $lineItem) {
                                    $lineItem->updateQuietly([
                                        'tax_total' => $lineItem->calculateTaxTotal()->getAmount(),
                                        'discount_total' => $lineItem->calculateDiscountTotal()->getAmount(),
                                    ]);
                                });

                                $subtotal = $bill->lineItems()->sum('subtotal') / 100;
                                $taxTotal = $bill->lineItems()->sum('tax_total') / 100;
                                $discountTotal = $bill->lineItems()->sum('discount_total') / 100;
                                $grandTotal = $subtotal + $taxTotal - $discountTotal;

                                $bill->updateQuietly([
                                    'subtotal' => $subtotal,
                                    'tax_total' => $taxTotal,
                                    'discount_total' => $discountTotal,
                                    'total' => $grandTotal,
                                ]);

                                $bill->refresh();

                                if (! $bill->initialTransaction) {
                                    $bill->createInitialTransaction();
                                } else {
                                    $bill->updateInitialTransaction();
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
                                    ->relationship('purchasableOffering', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $offeringId = $state;
                                        $offeringRecord = Offering::with(['purchaseTaxes', 'purchaseDiscounts'])->find($offeringId);

                                        if ($offeringRecord) {
                                            $set('description', $offeringRecord->description);
                                            $set('unit_price', $offeringRecord->price);
                                            $set('purchaseTaxes', $offeringRecord->purchaseTaxes->pluck('id')->toArray());
                                            $set('purchaseDiscounts', $offeringRecord->purchaseDiscounts->pluck('id')->toArray());
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
                                Forms\Components\Select::make('purchaseTaxes')
                                    ->relationship('purchaseTaxes', 'name')
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable(),
                                Forms\Components\Select::make('purchaseDiscounts')
                                    ->relationship('purchaseDiscounts', 'name')
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable(),
                                Forms\Components\Placeholder::make('total')
                                    ->hiddenLabel()
                                    ->content(function (Forms\Get $get) {
                                        $quantity = max((float) ($get('quantity') ?? 0), 0);
                                        $unitPrice = max((float) ($get('unit_price') ?? 0), 0);
                                        $purchaseTaxes = $get('purchaseTaxes') ?? [];
                                        $purchaseDiscounts = $get('purchaseDiscounts') ?? [];

                                        $subtotal = $quantity * $unitPrice;

                                        // Calculate tax amount based on subtotal
                                        $taxAmount = 0;
                                        if (! empty($purchaseTaxes)) {
                                            $taxRates = Adjustment::whereIn('id', $purchaseTaxes)->pluck('rate');
                                            $taxAmount = collect($taxRates)->sum(fn ($rate) => $subtotal * ($rate / 100));
                                        }

                                        // Calculate discount amount based on subtotal
                                        $discountAmount = 0;
                                        if (! empty($purchaseDiscounts)) {
                                            $discountRates = Adjustment::whereIn('id', $purchaseDiscounts)->pluck('rate');
                                            $discountAmount = collect($discountRates)->sum(fn ($rate) => $subtotal * ($rate / 100));
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
                                    ->view('filament.forms.components.bill-totals'),
                            ]),
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
                Tables\Columns\TextColumn::make('bill_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor.supplier_name')
                    ->sortable(),
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
                                ->money()
                                ->live(onBlur: true)
                                ->helperText(function (Bill $record, $state) {
                                    if (! CurrencyConverter::isValidAmount($state)) {
                                        return null;
                                    }

                                    $amountDue = $record->getRawOriginal('amount_due');
                                    $amount = CurrencyConverter::convertToCents($state);

                                    if ($amount <= 0) {
                                        return 'Please enter a valid positive amount';
                                    }

                                    $newAmountDue = $amountDue - $amount;

                                    return match (true) {
                                        $newAmountDue > 0 => 'Amount due after payment will be ' . CurrencyConverter::formatCentsToMoney($newAmountDue),
                                        $newAmountDue === 0 => 'Bill will be fully paid',
                                        default => 'Amount exceeds bill total by ' . CurrencyConverter::formatCentsToMoney(abs($newAmountDue)),
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
                            $cantRecordPayments = $records->contains(fn (Bill $bill) => ! $bill->canRecordPayment());

                            if ($cantRecordPayments) {
                                Notification::make()
                                    ->title('Payment Recording Failed')
                                    ->body('Bills that are either paid or voided cannot be processed through bulk payments. Please adjust your selection and try again.')
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
}
