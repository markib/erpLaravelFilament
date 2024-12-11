<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <!-- Form Container -->
            @if(method_exists($this, 'filtersForm'))
                {{ $this->filtersForm }}
            @endif

            <!-- Grouping Button and Column Toggle -->
            @if($this->hasToggleableColumns())
                <x-filament-tables::column-toggle.dropdown
                    :form="$this->getTableColumnToggleForm()"
                    :trigger-action="$this->getToggleColumnsTriggerAction()"
                />
            @endif

            <div class="inline-flex items-center min-w-0 md:min-w-[9.5rem] justify-end">
                {{ $this->applyFiltersAction }}
            </div>
        </div>
    </x-filament::section>


    <x-filament::section>
        <!-- Summary Section -->
        @if($this->reportLoaded)
            <div
                class="flex flex-col md:flex-row items-center md:items-end text-center justify-center gap-4 md:gap-8">
                @foreach($this->report->getSummary() as $summary)
                    <div class="text-sm">
                        <div class="text-gray-600 font-medium mb-2">{{ $summary['label'] }}</div>

                        @php
                            $isNetCashFlow = $summary['label'] === 'Net Cash Flow';
                            $isPositive = money($summary['value'], \App\Utilities\Currency\CurrencyAccessor::getDefaultCurrency())->isPositive();
                        @endphp

                        <strong
                            @class([
                                'text-lg',
                                'text-green-700' => $isNetCashFlow && $isPositive,
                                'text-danger-700' => $isNetCashFlow && ! $isPositive,
                            ])
                        >
                            {{ $summary['value'] }}
                        </strong>
                    </div>

                    @if(! $loop->last)
                        <div class="flex items-center justify-center px-2">
                            <strong class="text-lg">
                                {{ $loop->remaining === 1 ? '=' : '-' }}
                            </strong>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::tabs>
        <x-filament::tabs.item
            :active="$activeTab === 'summary'"
            wire:click="$set('activeTab', 'summary')"
        >
            Summary
        </x-filament::tabs.item>

        <x-filament::tabs.item
            :active="$activeTab === 'details'"
            wire:click="$set('activeTab', 'details')"
        >
            Details
        </x-filament::tabs.item>
    </x-filament::tabs>

    <x-company.tables.container :report-loaded="$this->reportLoaded">
        @if($this->report)
            @if($activeTab === 'summary')
                <x-company.tables.reports.cash-flow-statement-summary :report="$this->report"/>
            @elseif($activeTab === 'details')
                <x-company.tables.reports.cash-flow-statement :report="$this->report"/>
            @endif
        @endif
    </x-company.tables.container>
</x-filament-panels::page>

