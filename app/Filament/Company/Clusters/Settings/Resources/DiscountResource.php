<?php

namespace App\Filament\Company\Clusters\Settings\Resources;

use App\Concerns\NotifiesOnDelete;
use App\Enums\Setting\DateFormat;
use App\Enums\Setting\DiscountComputation;
use App\Enums\Setting\DiscountScope;
use App\Enums\Setting\DiscountType;
use App\Enums\Setting\TimeFormat;
use App\Filament\Company\Clusters\Settings;
use App\Filament\Company\Clusters\Settings\Resources\DiscountResource\Pages;
use App\Models\Setting\Discount;
use App\Models\Setting\Localization;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Wallo\FilamentSelectify\Components\ToggleButton;

class DiscountResource extends Resource
{
    use NotifiesOnDelete;

    protected static ?string $model = Discount::class;

    protected static ?string $modelLabel = 'Discount';

    protected static ?string $cluster = Settings::class;

    public static function getModelLabel(): string
    {
        $modelLabel = static::$modelLabel;

        return translate($modelLabel);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->localizeLabel()
                            ->maxLength(255)
                            ->rule(static function (Forms\Get $get, Forms\Components\Component $component): Closure {
                                return static function (string $attribute, $value, Closure $fail) use ($component, $get) {
                                    $existingDiscount = Discount::where('name', $value)
                                        ->whereKeyNot($component->getRecord()?->getKey())
                                        ->where('type', $get('type'))
                                        ->first();

                                    if ($existingDiscount) {
                                        $message = translate('The :Type :record ":name" already exists.', [
                                            'Type' => $existingDiscount->type->getLabel(),
                                            'record' => strtolower(static::getModelLabel()),
                                            'name' => $value,
                                        ]);

                                        $fail($message);
                                    }
                                };
                            }),
                        Forms\Components\TextInput::make('description')
                            ->localizeLabel(),
                        Forms\Components\Select::make('computation')
                            ->localizeLabel()
                            ->options(DiscountComputation::class)
                            ->default(DiscountComputation::Percentage)
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('rate')
                            ->localizeLabel()
                            ->rate(static fn (Forms\Get $get) => $get('computation'))
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->localizeLabel()
                            ->options(DiscountType::class)
                            ->default(DiscountType::Sales)
                            ->required(),
                        Forms\Components\Select::make('scope')
                            ->localizeLabel()
                            ->options(DiscountScope::class)
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('start_date')
                            ->localizeLabel()
                            ->beforeOrEqual('end_date')
                            ->seconds(false)
                            ->disabled(static fn (string $operation, ?Discount $record = null) => $operation === 'edit' && $record?->start_date?->isPast() ?? false)
                            ->helperText(static fn (Forms\Components\DateTimePicker $component) => $component->isDisabled() ? 'Start date cannot be changed after the discount has begun.' : null),
                        Forms\Components\DateTimePicker::make('end_date')
                            ->localizeLabel()
                            ->afterOrEqual('start_date')
                            ->seconds(false),
                        ToggleButton::make('enabled')
                            ->localizeLabel('Default')
                            ->onLabel(Discount::enabledLabel())
                            ->offLabel(Discount::disabledLabel()),
                    ])->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->localizeLabel()
                    ->weight(FontWeight::Medium)
                    ->icon(static fn (Discount $record) => $record->isEnabled() ? 'heroicon-o-lock-closed' : null)
                    ->tooltip(static function (Discount $record) {
                        if ($record->isDisabled()) {
                            return null;
                        }

                        return translate('Default :Type :Record', [
                            'Type' => $record->type->getLabel(),
                            'Record' => static::getModelLabel(),
                        ]);
                    })
                    ->iconPosition('after')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rate')
                    ->localizeLabel()
                    ->rate(static fn (Discount $record) => $record->computation->value)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->localizeLabel()
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->localizeLabel()
                    ->formatStateUsing(static function (Discount $record) {
                        $dateFormat = Localization::firstOrFail()->date_format->value ?? DateFormat::DEFAULT;
                        $timeFormat = Localization::firstOrFail()->time_format->value ?? TimeFormat::DEFAULT;

                        return $record->start_date ? $record->start_date->format("{$dateFormat} {$timeFormat}") : 'N/A';
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->localizeLabel()
                    ->formatStateUsing(static function (Discount $record) {
                        $dateFormat = Localization::firstOrFail()->date_format->value ?? DateFormat::DEFAULT;
                        $timeFormat = Localization::firstOrFail()->time_format->value ?? TimeFormat::DEFAULT;

                        return $record->end_date ? $record->end_date->format("{$dateFormat} {$timeFormat}") : 'N/A';
                    })
                    ->color(static fn (Discount $record) => $record->end_date?->isPast() ? 'danger' : null)
                    ->searchable()
                    ->sortable(),
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
            ])
            ->checkIfRecordIsSelectableUsing(static function (Discount $record) {
                return $record->isDisabled();
            })
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscounts::route('/'),
            'create' => Pages\CreateDiscount::route('/create'),
            'edit' => Pages\EditDiscount::route('/{record}/edit'),
        ];
    }
}
