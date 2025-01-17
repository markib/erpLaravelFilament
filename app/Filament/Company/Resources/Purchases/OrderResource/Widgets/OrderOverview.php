<?php

namespace App\Filament\Company\Resources\Purchases\OrderResource\Widgets;

use App\Enums\Accounting\OrderStatus;
use App\Filament\Company\Resources\Purchases\OrderResource\Pages\ListOrders;
use App\Filament\Widgets\EnhancedStatsOverviewWidget;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Number;

class OrderOverview extends EnhancedStatsOverviewWidget
{
    // protected static string $view = 'filament.company.resources.purchases.order-resource.widgets.purchases.order-overview';
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListOrders::class;
    }

    protected function getStats(): array
    {
        $activeOrders = $this->getPageTableQuery()->active();

        $totalActiveCount = $activeOrders->count();
        $totalActiveAmount = $activeOrders->get()->sumMoneyInDefaultCurrency('total');

        $acceptedOrders = $this->getPageTableQuery()
            ->where('status', OrderStatus::Accepted);

        $totalAcceptedCount = $acceptedOrders->count();
        $totalAcceptedAmount = $acceptedOrders->get()->sumMoneyInDefaultCurrency('total');

        $convertedOrders = $this->getPageTableQuery()
            ->where('status', OrderStatus::Converted);

        $totalConvertedCount = $convertedOrders->count();
        $totalOrdersCount = $this->getPageTableQuery()->count();

        $percentConverted = $totalOrdersCount > 0
            ? Number::percentage(($totalConvertedCount / $totalOrdersCount) * 100, maxPrecision: 1)
            : Number::percentage(0, maxPrecision: 1);

        $totalOrderAmount = $this->getPageTableQuery()
            ->get()
            ->sumMoneyInDefaultCurrency('total');

        $averageOrderTotal = $totalOrdersCount > 0
            ? (int) round($totalOrderAmount / $totalOrdersCount)
            : 0;

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make('Active Orders', CurrencyConverter::formatCentsToMoney($totalActiveAmount))
                ->suffix(CurrencyAccessor::getDefaultCurrency())
                ->description($totalActiveCount . ' active orders'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Accepted Orders', CurrencyConverter::formatCentsToMoney($totalAcceptedAmount))
                ->suffix(CurrencyAccessor::getDefaultCurrency())
                ->description($totalAcceptedCount . ' accepted'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Converted Orders', $percentConverted)
                ->suffix('converted')
                ->description($totalConvertedCount . ' converted'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Average Order Total', CurrencyConverter::formatCentsToMoney($averageOrderTotal))
                ->suffix(CurrencyAccessor::getDefaultCurrency()),
        ];
    }
}
