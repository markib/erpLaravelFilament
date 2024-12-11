<?php

namespace App\DTO;

use Illuminate\Support\Carbon;

class ReportDTO
{
    public function __construct(
        /**
         * @var AccountCategoryDTO[]
         */
        public array $categories,
        public ?AccountBalanceDTO $overallTotal = null,
        public array $fields = [],
        public ?string $reportType = null,
        public ?CashFlowOverviewDTO $overview = null,
        public ?Carbon $startDate = null,
        public ?Carbon $endDate = null,
    ) {}
}
