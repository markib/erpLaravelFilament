<?php

namespace App\Listeners;

use App\Enums\Setting\DateFormat;
use App\Enums\Setting\Font;
use App\Enums\Setting\PrimaryColor;
use App\Enums\Setting\RecordsPerPage;
use App\Enums\Setting\TableSortDirection;
use App\Enums\Setting\WeekStart;
use App\Events\CompanyConfigured;
use App\Utilities\Currency\ConfigureCurrencies;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Resources\Components\Tab as ResourcesTab;
use Filament\Support\Facades\FilamentColor;
use Filament\Tables\Table;

class ConfigureCompanyDefault
{
    /**
     * Handle the event.
     */
    public function handle(CompanyConfigured $event): void
    {
        $company = $event->company;

        session([
            'current_company_id' => $company->id,
            'default_language' => $company->locale->language ?? config('transmatic.source_locale'),
            'default_timezone' => $company->locale->timezone ?? config('app.timezone'),
            'default_pagination_page_option' => $company->appearance->records_per_page->value ?? RecordsPerPage::DEFAULT,
            'default_sort' => $company->appearance->table_sort_direction->value ?? TableSortDirection::DEFAULT,
            'default_primary_color' => $company->appearance->primary_color->value ?? PrimaryColor::DEFAULT,
            'default_font' => $company->appearance->font->value ?? Font::DEFAULT,
            'default_date_format' => $company->locale->date_format->value ?? DateFormat::DEFAULT,
            'default_week_start' => $company->locale->week_start->value ?? WeekStart::DEFAULT,
        ]);

        app()->setLocale(session('default_language'));
        locale_set_default(session('default_language'));
        config(['app.timezone' => session('default_timezone')]);
        date_default_timezone_set(session('default_timezone'));

        $paginationPageOptions = RecordsPerPage::caseValues();

        Table::configureUsing(static function (Table $table) use ($paginationPageOptions): void {

            $table
                ->paginationPageOptions($paginationPageOptions)
                ->defaultSort(column: 'id', direction: session('default_sort'))
                ->defaultPaginationPageOption(session('default_pagination_page_option'));
        }, isImportant: true);

        FilamentColor::register([
            'primary' => PrimaryColor::from(session('default_primary_color'))->getColor(),
        ]);

        Filament::getPanel('company')
            ->font(session('default_font'))
            ->brandName($company->name);

        DatePicker::configureUsing(static function (DatePicker $component) {
            $component
                ->displayFormat(session('default_date_format'))
                ->firstDayOfWeek(session('default_week_start'));
        });

        Tab::configureUsing(static function (Tab $tab) {
            $label = $tab->getLabel();

            if ($label) {
                $translatedLabel = translate($label);

                $tab->label(ucwords($translatedLabel));
            }
        }, isImportant: true);

        Section::configureUsing(static function (Section $section): void {
            $heading = $section->getHeading();

            if ($heading) {
                $translatedHeading = translate($heading);

                $section->heading(ucfirst($translatedHeading));
            }
        }, isImportant: true);

        ResourcesTab::configureUsing(static function (ResourcesTab $tab): void {
            $tab->localizeLabel();
        }, isImportant: true);

        ConfigureCurrencies::syncCurrencies();
    }
}
