<?php

namespace App\Filament\Widgets;

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\EstimateStatus;
use App\Enums\Accounting\OrderStatus;
use App\Enums\Common\ItemType;
use App\Filament\Widgets\EnhancedStatsOverviewWidget\EnhancedStat;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Order;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Illuminate\Support\Number;

class EnhancedStatsOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    // protected static bool $isLazy = false;

    protected function getStats(): array
    {

        $startDate = ! is_null($this->filters['startDate'] ?? null) ?
            Carbon::parse($this->filters['startDate']) :
            now()->subDays(90);

        $endDate = ! is_null($this->filters['endDate'] ?? null) ?
            Carbon::parse($this->filters['endDate']) :
            now();

        $previousStartDate = (clone $startDate)->subDays($startDate->diffInDays($endDate));
        $previousEndDate = (clone $endDate)->subDays($startDate->diffInDays($endDate));

        // Calculate for current period
        $currentPeriodStats = $this->calculateStats($startDate, $endDate);
        // Calculate for previous period
        $previousPeriodStats = $this->calculateStats($previousStartDate, $previousEndDate);

        $formatNumber = function (int $number): string {
            if ($number < 1000) {
                return (string) Number::format($number, 0);
            }

            if ($number < 1000000) {
                return Number::format($number / 1000, 2) . 'k';
            }

            return Number::format($number / 1000000, 2) . 'm';
        };
        $calculateDifference = function ($current, $previous) {
            if ($previous == 0) {
                return $current > 0 ? 100 : 0;
            }

            return (($current - $previous) / $previous) * 100;
        };

        $newVendorsDifference = $calculateDifference($currentPeriodStats['newVendors'], $previousPeriodStats['newVendors']);
        $newCustomersDifference = $calculateDifference($currentPeriodStats['newCustomers'], $previousPeriodStats['newCustomers']);
        $activeOrdersDifference = $calculateDifference($currentPeriodStats['activeOrders'], $previousPeriodStats['activeOrders']);
        $draftOrdersDifference = $calculateDifference($currentPeriodStats['draftOrders'], $previousPeriodStats['draftOrders']);
        $unpaidBillsDifference = $calculateDifference($currentPeriodStats['unpaidBills'], $previousPeriodStats['unpaidBills']);
        $paidBillsDifference = $calculateDifference($currentPeriodStats['paidBills'], $previousPeriodStats['paidBills']);
        $draftEstimatesDifference = $calculateDifference($currentPeriodStats['draftEstimates'], $previousPeriodStats['draftEstimates']);
        $activeEstimatesDifference = $calculateDifference($currentPeriodStats['activeEstimates'], $previousPeriodStats['activeEstimates']);
        $unpaidInvoicesDifference = $calculateDifference($currentPeriodStats['unpaidInvoices'], $previousPeriodStats['unpaidInvoices']);
        $paidInvoicesDifference = $calculateDifference($currentPeriodStats['paidInvoices'], $previousPeriodStats['paidInvoices']);

        return [
            // EnhancedStat::make('Total Users', \App\Models\User::count())
            //     ->description('Number of registered users')
            //     ->color('primary'),
            EnhancedStat::make('New Vendor', $formatNumber($currentPeriodStats['newVendors']))
                ->description(number_format(abs($newVendorsDifference), 2) . '% ' . ($newVendorsDifference >= 0 ? 'increase' : 'decrease'))
                ->descriptionIcon($newVendorsDifference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart([17, 16, 14, 15, 14, 13, 12])
                ->color($newVendorsDifference >= 0 ? 'success' : 'danger'),
            EnhancedStat::make('New Customer', $formatNumber($currentPeriodStats['newCustomers']))
                ->description(number_format(abs($newCustomersDifference), 2) . '% ' . ($newCustomersDifference >= 0 ? 'increase' : 'decrease'))
                ->descriptionIcon($newCustomersDifference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart([17, 16, 14, 15, 14, 13, 12])
                ->color($newCustomersDifference >= 0 ? 'success' : 'danger'),
            EnhancedStat::make('Purchase Orders', $formatNumber($currentPeriodStats['activeOrders']) . ' Active | ' . $formatNumber($currentPeriodStats['draftOrders']) . ' Draft')
                ->description(
                    number_format(abs($activeOrdersDifference), 2) . '% ' . ($activeOrdersDifference >= 0 ? 'increase' : 'decrease') . ' in Active | ' .
                        number_format(abs($draftOrdersDifference), 2) . '% ' . ($draftOrdersDifference >= 0 ? 'increase' : 'decrease') . ' in Draft'
                )
                ->descriptionIcon($activeOrdersDifference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart([20, 22, 24, 23, 21, 25, 27])
                ->color($activeOrdersDifference >= 0 ? 'success' : 'danger'),
            EnhancedStat::make('Purchase Bills', $formatNumber($currentPeriodStats['unpaidBills']) . ' Unpaid | ' . $formatNumber($currentPeriodStats['paidBills']) . ' Paid')
                ->description(
                    number_format(abs($unpaidBillsDifference), 2) . '% ' . ($paidBillsDifference >= 0 ? 'increase' : 'decrease') . ' in Unpaid | ' .
                    number_format(abs($paidBillsDifference), 2) . '% ' . ($unpaidBillsDifference >= 0 ? 'increase' : 'decrease') . ' in Paid'
                )
                ->descriptionIcon($unpaidBillsDifference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart([20, 22, 24, 23,
                    21,
                    25,
                    27,
                ])
                ->color($unpaidBillsDifference >= 0 ? 'success' : 'danger'),
            EnhancedStat::make('Sale Estimates', $formatNumber($currentPeriodStats['activeEstimates']) . ' Unpaid | ' . $formatNumber($currentPeriodStats['draftEstimates']) . ' Paid')
                ->description(
                    number_format(abs($draftEstimatesDifference), 2) . '% ' . ($activeEstimatesDifference >= 0 ? 'increase' : 'decrease') . ' in Active | ' .
                    number_format(abs($activeEstimatesDifference), 2) . '% ' . ($draftEstimatesDifference >= 0 ? 'increase' : 'decrease') . ' in Draft'
                )
                ->descriptionIcon($draftEstimatesDifference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart([
                    20,
                    22,
                    24,
                    23,
                    21,
                    25,
                    27,
                ])
                ->color($draftEstimatesDifference >= 0 ? 'success' : 'danger'),
            EnhancedStat::make('Sale Invoices', $formatNumber($currentPeriodStats['unpaidInvoices']) . ' Unpaid | ' . $formatNumber($currentPeriodStats['paidInvoices']) . ' Paid')
                ->description(
                    number_format(abs($unpaidInvoicesDifference), 2) . '% ' . ($paidInvoicesDifference >= 0 ? 'increase' : 'decrease') . ' in Unpaid | ' .
                    number_format(abs($paidInvoicesDifference), 2) . '% ' . ($unpaidInvoicesDifference >= 0 ? 'increase' : 'decrease') . ' in Paid'
                )
                ->descriptionIcon($unpaidInvoicesDifference >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart([
                    20,
                    22,
                    24,
                    23,
                    21,
                    25,
                    27,
                ])
                ->color($draftEstimatesDifference >= 0 ? 'success' : 'danger'),

        ];
    }

    private function calculateStats($startDate, $endDate)
    {

        $baseQuery = Order::inventoryProduct()
            ->createdBetween($startDate, $endDate);
        //->get();
        $totalDraftOrders = (clone $baseQuery)
            ->where('status', OrderStatus::Draft->value)
            ->count();

        $totalNewVendors = (clone $baseQuery)
            ->distinct('vendor_id')
            ->count();

        $totalNewOrders = (clone $baseQuery)
            ->active()
            ->count();

        $draftEstimates = Estimate::where('item_type', ItemType::inventory_product)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', EstimateStatus::Draft)
            ->count();
        $activeEstimates = Estimate::where('item_type', ItemType::inventory_product)
            ->whereBetween('created_at', [$startDate, $endDate])->active()->count();
        $totalNewCustomers = Estimate::where('item_type', ItemType::inventory_product)
            ->whereBetween('created_at', [$startDate, $endDate])->distinct('client_id')->count();
        // Calculating earnings and losses
        // $earningsLosses = EarningsLosses::whereIn('order_id', $orders->pluck('id'))->get();
        // $totalEarnings = $earningsLosses->sum('earnings');
        // $totalLosses = $earningsLosses->sum('losses');

        // Purchase (only 'purchase' orders)
        $purchases = Bill::where('item_type', ItemType::inventory_product)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalPurchases = $purchases->flatMap->lineItems->sum('quantity');
        $totalPurchaseCost = $purchases->flatMap->lineItems->sum('unit_price'); // Assuming 'total_amount' represents the cost

        // Calculate unpaid and paid bills
        $unpaidBills = Bill::whereBetween('created_at', [$startDate, $endDate])
            ->where('item_type', ItemType::inventory_product)
            ->unpaid()
            ->count();

        $paidBills = Bill::where('status', BillStatus::Paid)
            ->where('item_type', ItemType::inventory_product)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count();

        // Calculate unpaid and paid invoices
        $unpaidInvoices = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->where('item_type', ItemType::inventory_product)
            ->unpaid()
            ->count();

        $paidInvoices = Invoice::where('status', BillStatus::Paid)
            ->where('item_type', ItemType::inventory_product)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count();

        return [
            'newCustomers' => $totalNewCustomers,
            'newVendors' => $totalNewVendors,
            'Orders' => $totalNewOrders,
            'activeOrders' => $totalNewOrders,
            'draftOrders' => $totalDraftOrders,
            'draftEstimates' => $draftEstimates,
            'activeEstimates' => $activeEstimates,
            // 'earnings' => $totalEarnings,
            // 'losses' => $totalLosses,
            'unpaidBills' => $unpaidBills,
            'paidBills' => $paidBills,
            'unpaidInvoices' => $unpaidInvoices,
            'paidInvoices' => $paidInvoices,
        ];
    }
}
