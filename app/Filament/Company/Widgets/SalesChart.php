<?php

namespace App\Filament\Company\Widgets;

use App\Enums\Common\ItemType;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class SalesChart extends ChartWidget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.company.widgets.sales-chart';

    protected static ?string $heading = 'Sales Per Month';

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    public ?string $filter = 'current_year';

    public ?string $startDate = null;

    public ?string $endDate = null;

    protected ?array $cachedData = null;

    #[Locked]
    public ?string $dataChecksum = null;

    protected function getFormSchema(): array
    {
        return [

            Grid::make(2) // Adjusts width by setting columns
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Start Date')
                        ->visible(fn ($get) => $get('filter') === 'custom_range')
                        ->columnSpan(1), // Reducing width

                    DatePicker::make('endDate')
                        ->label('End Date')
                        ->visible(fn ($get) => $get('filter') === 'custom_range')
                        ->columnSpan(1), // Keeping it small
                ]),
        ];
    }

    #[On('refreshSalesChartData')]
    public function getData(): array
    {
        // logger()->info('getData method called in SalesChart');
        $now = now();

        switch ($this->filter) {
            case 'current_year':
                $startDate = $now->copy()->startOfYear();
                $endDate = $now->copy()->endOfYear();

                break;
            case 'last_year':
                $startDate = $now->copy()->subYear()->startOfYear();
                $endDate = $now->copy()->subYear()->endOfYear();

                break;
            case 'custom_range':
                $startDate = $this->startDate ? Carbon::parse($this->startDate) : $now->copy()->startOfYear();
                $endDate = $this->endDate ? Carbon::parse($this->endDate) : $now->copy()->endOfYear();

                break;
            default:
                $startDate = $now->copy()->startOfYear();
                $endDate = $now->copy()->endOfYear();
        }

        $salesOrders = DB::table('estimates')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('item_type', ItemType::inventory_product)
            ->selectRaw('EXTRACT(MONTH FROM created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $salesInvoices = DB::table('invoices')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('item_type', ItemType::inventory_product)
            ->selectRaw('EXTRACT(MONTH FROM created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = $salesOrders->pluck('month')->map(function ($month) {
            return \Carbon\Carbon::create()->month((int) $month)->format('M');
        })->toArray();

        $soData = $salesOrders->pluck('count')->map(fn ($count) => (int) $count)->toArray();
        $siData = $salesInvoices->pluck('count')->map(fn ($count) => (int) $count)->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Sales Orders',
                    'data' => $soData,
                    'borderColor' => '#4a4cc4',
                    'backgroundColor' => 'rgba(74, 76, 196, 0.1)',
                ],
                [
                    'label' => 'Sales Invoices',
                    'data' => $siData,
                    'borderColor' => '#51a6bb',
                    'backgroundColor' => 'rgba(81, 166, 187, 0.1)',
                ],
            ],
        ];
    }

    public function mount(): void
    {
        // $this->updateChartData();
        $this->dataChecksum = $this->generateDataChecksum();
    }

    protected function generateDataChecksum(): string
    {
        return md5(json_encode($this->getCachedData()));
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'ticks' => [
                        'stepSize' => 1,
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'current_year' => 'This Year',
            'last_year' => 'Last Year',
            'custom_range' => 'Custom Date Range',
        ];
    }

    public function applyDateFilter()
    {

        $this->dispatch('refreshSalesChartData');
    }

    protected function getCachedData(): array
    {
        return $this->cachedData ??= $this->getData();
    }

    // public function updateChartData(): void
    // {

    //     $newDataChecksum = $this->generateDataChecksum();

    //     if ($newDataChecksum !== $this->dataChecksum) {
    //         $this->dataChecksum = $newDataChecksum;

    //         $this->dispatch('updateChartData', data: $this->getCachedData());
    //     }
    // }

    public function updatedFilter()
    {
        $this->cachedData = null;
        // $this->updateChartData();
    }

    public function updatedStartDate()
    {
        if ($this->filter === 'custom_range') {
            $this->cachedData = null;
            // $this->updateChartData();
        }
    }

    public function updatedEndDate()
    {
        if ($this->filter === 'custom_range') {
            $this->cachedData = null;
            // $this->updateChartData();
        }
    }

    public function render(): View
    {
        // $this->updateChartData();

        return view('filament.company.widgets.sales-chart', [
            'chartData' => $this->getData(),
            'cachedData' => $this->getCachedData(),
            'chartOptions' => $this->getOptions(),
            'chartType' => $this->getType(),
            'form' => $this->getFormSchema(),
        ]);
    }
}
