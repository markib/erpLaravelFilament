<?php

namespace App\Providers;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Setting\DateFormat;
use App\Models\Accounting\AccountSubtype;
use App\Models\Setting\Localization;
use App\Utilities\Accounting\AccountCode;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use BackedEnum;
use Carbon\CarbonInterface;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        TextInput::macro('money', function (string | Closure | null $currency = null, bool $useAffix = true): static {
            $currency ??= CurrencyAccessor::getDefaultCurrency();

            if ($useAffix) {
                $this
                    ->prefix(static function (TextInput $component) use ($currency) {
                        $currency = $component->evaluate($currency);

                        return currency($currency)->getPrefix();
                    })
                    ->suffix(static function (TextInput $component) use ($currency) {
                        $currency = $component->evaluate($currency);

                        return currency($currency)->getSuffix();
                    });
            }

            $this->mask(static function (TextInput $component) use ($currency) {
                $currency = $component->evaluate($currency);

                return moneyMask($currency);
            });

            return $this;
        });

        TextInput::macro('rate', function (string | Closure | null $computation = null, string | Closure | null $currency = null, bool $showAffix = true): static {
            return $this
                ->when(
                    $showAffix,
                    fn (TextInput $component) => $component
                        ->prefix(function (TextInput $component) use ($computation, $currency) {
                            $evaluatedComputation = $component->evaluate($computation);
                            $evaluatedCurrency = $component->evaluate($currency);

                            return ratePrefix($evaluatedComputation, $evaluatedCurrency);
                        })
                        ->suffix(function (TextInput $component) use ($computation, $currency) {
                            $evaluatedComputation = $component->evaluate($computation);
                            $evaluatedCurrency = $component->evaluate($currency);

                            return rateSuffix($evaluatedComputation, $evaluatedCurrency);
                        })
                )
                ->mask(static function (TextInput $component) use ($computation, $currency) {
                    $computation = $component->evaluate($computation);
                    $currency = $component->evaluate($currency);

                    $computationEnum = AdjustmentComputation::parse($computation);

                    if ($computationEnum->isPercentage()) {
                        return rateMask(computation: $computation);
                    }

                    return moneyMask($currency);
                })
                ->rule(static function (TextInput $component) use ($computation) {
                    return static function (string $attribute, $value, Closure $fail) use ($computation, $component) {
                        $computation = $component->evaluate($computation);
                        $numericValue = (float) $value;

                        if ($computation instanceof BackedEnum) {
                            $computation = $computation->value;
                        }

                        if ($computation === 'percentage' || $computation === 'compound') {
                            if ($numericValue < 0 || $numericValue > 100) {
                                $fail(translate('The rate must be between 0 and 100.'));
                            }
                        } elseif ($computation === 'fixed' && $numericValue < 0) {
                            $fail(translate('The rate must be greater than 0.'));
                        }
                    };
                });
        });

        TextColumn::macro('defaultDateFormat', function (): static {
            $localization = Localization::firstOrFail();

            $dateFormat = $localization->date_format->value ?? DateFormat::DEFAULT;
            $timezone = $localization->timezone ?? Carbon::now()->timezoneName;

            $this->date($dateFormat, $timezone);

            return $this;
        });

        DatePicker::macro('defaultDateFormat', function (): static {
            $localization = Localization::firstOrFail();

            $dateFormat = $localization->date_format->value ?? DateFormat::DEFAULT;
            $timezone = $localization->timezone ?? Carbon::now()->timezoneName;

            $this->displayFormat($dateFormat)
                ->timezone($timezone);

            return $this;
        });

        TextColumn::macro('currency', function (string | Closure | null $currency = null, ?bool $convert = null): static {
            $currency ??= CurrencyAccessor::getDefaultCurrency();
            $convert ??= true;

            $this->formatStateUsing(static function (TextColumn $column, $state) use ($currency, $convert): ?string {
                if (blank($state)) {
                    return null;
                }

                $currency = $column->evaluate($currency);
                $convert = $column->evaluate($convert);

                return money($state, $currency, $convert)->format();
            });

            return $this;
        });

        TextEntry::macro('currency', function (string | Closure | null $currency = null, ?bool $convert = null): static {
            $currency ??= CurrencyAccessor::getDefaultCurrency();
            $convert ??= true;

            $this->formatStateUsing(static function (TextEntry $entry, $state) use ($currency, $convert): ?string {
                if (blank($state)) {
                    return null;
                }

                $currency = $entry->evaluate($currency);
                $convert = $entry->evaluate($convert);

                return money($state, $currency, $convert)->format();
            });

            return $this;
        });

        TextColumn::macro('currencyWithConversion', function (string | Closure | null $currency = null): static {
            $currency ??= CurrencyAccessor::getDefaultCurrency();

            $this->formatStateUsing(static function (TextColumn $column, $state) use ($currency): ?string {
                if (blank($state)) {
                    return null;
                }

                $currency = $column->evaluate($currency);

                return CurrencyConverter::formatToMoney($state, $currency);
            });

            $this->description(static function (TextColumn $column, $state) use ($currency): ?string {
                if (blank($state)) {
                    return null;
                }

                $oldCurrency = $column->evaluate($currency);
                $newCurrency = CurrencyAccessor::getDefaultCurrency();

                if ($oldCurrency === $newCurrency) {
                    return null;
                }

                $balanceInCents = CurrencyConverter::convertToCents($state, $oldCurrency);

                $convertedBalanceInCents = CurrencyConverter::convertBalance($balanceInCents, $oldCurrency, $newCurrency);

                return CurrencyConverter::formatCentsToMoney($convertedBalanceInCents, $newCurrency, true);
            });

            return $this;
        });

        TextEntry::macro('currencyWithConversion', function (string | Closure | null $currency = null): static {
            $currency ??= CurrencyAccessor::getDefaultCurrency();

            $this->formatStateUsing(static function (TextEntry $entry, $state) use ($currency): ?string {
                if (blank($state)) {
                    return null;
                }

                $currency = $entry->evaluate($currency);

                return CurrencyConverter::formatToMoney($state, $currency);
            });

            $this->helperText(static function (TextEntry $entry, $state) use ($currency): ?string {
                if (blank($state)) {
                    return null;
                }

                $oldCurrency = $entry->evaluate($currency);
                $newCurrency = CurrencyAccessor::getDefaultCurrency();

                if ($oldCurrency === $newCurrency) {
                    return null;
                }

                $balanceInCents = CurrencyConverter::convertToCents($state, $oldCurrency);
                $convertedBalanceInCents = CurrencyConverter::convertBalance($balanceInCents, $oldCurrency, $newCurrency);

                return CurrencyConverter::formatCentsToMoney($convertedBalanceInCents, $newCurrency, true);
            });

            return $this;
        });

        Field::macro('validateAccountCode', function (string | Closure | null $subtype = null): static {
            $this
                ->rules([
                    fn (Field $component): Closure => static function (string $attribute, $value, Closure $fail) use ($subtype, $component) {
                        $subtype = $component->evaluate($subtype);
                        $chartSubtype = AccountSubtype::find($subtype);
                        $type = $chartSubtype->type;

                        if (! AccountCode::isValidCode($value, $type)) {
                            $message = AccountCode::getMessage($type);

                            $fail($message);
                        }
                    },
                ]);

            return $this;
        });

        TextColumn::macro('rate', function (string | Closure | null $computation = null): static {
            $this->formatStateUsing(static function (TextColumn $column, $state) use ($computation): ?string {
                $computation = $column->evaluate($computation);

                return rateFormat(state: $state, computation: $computation);
            });

            return $this;
        });

        Field::macro('softRequired', function (): static {
            $this
                ->required()
                ->markAsRequired(false);

            return $this;
        });

        TextColumn::macro('asRelativeDay', function (?string $timezone = null): static {
            $this->formatStateUsing(function (TextColumn $column, mixed $state) use ($timezone) {
                if (blank($state)) {
                    return null;
                }

                $date = Carbon::parse($state)
                    ->setTimezone($timezone ?? $column->getTimezone());

                if ($date->isToday()) {
                    return 'Today';
                }

                return $date->diffForHumans([
                    'options' => CarbonInterface::ONE_DAY_WORDS,
                ]);
            });

            return $this;
        });

        TextEntry::macro('asRelativeDay', function (?string $timezone = null): static {
            $this->formatStateUsing(function (TextEntry $entry, mixed $state) use ($timezone) {
                if (blank($state)) {
                    return null;
                }

                $date = Carbon::parse($state)
                    ->setTimezone($timezone ?? $entry->getTimezone());

                if ($date->isToday()) {
                    return 'Today';
                }

                return $date->diffForHumans([
                    'options' => CarbonInterface::ONE_DAY_WORDS,
                ]);
            });

            return $this;
        });

        Money::macro('swapAmountFor', function ($newCurrency) {
            $oldCurrency = $this->currency->getCurrency();
            $balanceInSubunits = $this->getAmount();

            $oldCurrencySubunit = currency($oldCurrency)->getSubunit();
            $newCurrencySubunit = currency($newCurrency)->getSubunit();

            $balanceInMajorUnits = $balanceInSubunits / $oldCurrencySubunit;

            $oldRate = currency($oldCurrency)->getRate();
            $newRate = currency($newCurrency)->getRate();

            $ratio = $newRate / $oldRate;
            $convertedBalanceInMajorUnits = $balanceInMajorUnits * $ratio;

            $roundedConvertedBalanceInMajorUnits = round($convertedBalanceInMajorUnits, currency($newCurrency)->getPrecision());

            $convertedBalanceInSubunits = $roundedConvertedBalanceInMajorUnits * $newCurrencySubunit;

            return (int) round($convertedBalanceInSubunits);
        });

        Money::macro('formatWithCode', function (bool $codeBefore = false) {
            $formatted = $this->format();

            $currencyCode = $this->currency->getCurrency();

            if ($codeBefore) {
                return $currencyCode . ' ' . $formatted;
            }

            return $formatted . ' ' . $currencyCode;
        });

        Currency::macro('getEntity', function () {
            $currencyCode = $this->getCurrency();

            $entity = config("money.currencies.{$currencyCode}.entity");

            return $entity ?? $currencyCode;
        });

        Currency::macro('getCodePrefix', function () {
            if ($this->isSymbolFirst()) {
                return '';
            }

            return ' ' . $this->getCurrency();
        });

        Currency::macro('getCodeSuffix', function () {
            if ($this->isSymbolFirst()) {
                return ' ' . $this->getCurrency();
            }

            return '';
        });

        Carbon::macro('toDefaultDateFormat', function () {
            $localization = Localization::firstOrFail();

            $dateFormat = $localization->date_format->value ?? DateFormat::DEFAULT;
            $timezone = $localization->timezone ?? Carbon::now()->timezoneName;

            return $this->setTimezone($timezone)->format($dateFormat);
        });
    }
}
