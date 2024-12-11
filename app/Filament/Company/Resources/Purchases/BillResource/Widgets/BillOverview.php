<?php

namespace App\Filament\Company\Resources\Purchases\BillResource\Widgets;

use App\Enums\Accounting\BillStatus;
use App\Filament\Company\Resources\Purchases\BillResource\Pages\ListBills;
use App\Filament\Widgets\EnhancedStatsOverviewWidget;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Number;

class BillOverview extends EnhancedStatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListBills::class;
    }

    protected function getStats(): array
    {
        $unpaidBills = $this->getPageTableQuery()
            ->whereIn('status', [BillStatus::Unpaid, BillStatus::Partial, BillStatus::Overdue]);

        $amountToPay = $unpaidBills->sum('amount_due');

        $amountOverdue = $unpaidBills
            ->clone()
            ->where('status', BillStatus::Overdue)
            ->sum('amount_due');

        $amountDueWithin7Days = $unpaidBills
            ->clone()
            ->whereBetween('due_date', [today(), today()->addWeek()])
            ->sum('amount_due');

        $averagePaymentTime = $this->getPageTableQuery()
            ->whereNotNull('paid_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, date, paid_at)) as avg_days')
            ->value('avg_days');

        $averagePaymentTimeFormatted = Number::format($averagePaymentTime ?? 0, maxPrecision: 1);

        $lastMonthTotal = $this->getPageTableQuery()
            ->where('status', BillStatus::Paid)
            ->whereBetween('date', [
                today()->subMonth()->startOfMonth(),
                today()->subMonth()->endOfMonth(),
            ])
            ->sum('amount_paid');

        return [
            EnhancedStatsOverviewWidget\EnhancedStat::make('Total To Pay', CurrencyConverter::formatCentsToMoney($amountToPay))
                ->suffix(CurrencyAccessor::getDefaultCurrency())
                ->description('Includes ' . CurrencyConverter::formatCentsToMoney($amountOverdue) . ' overdue'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Due Within 7 Days', CurrencyConverter::formatCentsToMoney($amountDueWithin7Days))
                ->suffix(CurrencyAccessor::getDefaultCurrency()),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Average Payment Time', $averagePaymentTimeFormatted)
                ->suffix('days'),

            EnhancedStatsOverviewWidget\EnhancedStat::make('Paid Last Month', CurrencyConverter::formatCentsToMoney($lastMonthTotal))
                ->suffix(CurrencyAccessor::getDefaultCurrency()),
        ];
    }
}
