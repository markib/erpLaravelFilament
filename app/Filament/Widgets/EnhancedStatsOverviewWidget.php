<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class EnhancedStatsOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            //
        ];
    }
}
