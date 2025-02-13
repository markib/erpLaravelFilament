@php
use Filament\Support\Facades\FilamentView;

$color = $this->getColor();
$heading = $this->getHeading();
$description = $this->getDescription();
$filters = $this->getFilters();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :description="$description" :heading="$heading">
        @if ($filters)
        <x-slot name="headerEnd">
            <x-filament::input.wrapper inline-prefix wire:target="filter" class="w-max sm:-my-2">
                <x-filament::input.select inline-prefix wire:model.live="filter">
                    @foreach ($filters as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </x-slot>
        @endif

        <!-- Custom Date Selector -->
        @if ($filter === 'custom_range')
        <x-filament::input.wrapper inline-prefix wire:target="dateFilter" class="w-max sm:-my-2">

            <input type="date" wire:model="startDate" class="fi-input w-full" placeholder="Start Date">
            <input type="date" wire:model="endDate" class="fi-input w-full" placeholder="End Date">
            <button wire:click="applyDateFilter" class="fi-button fi-button-primary">Filter</button>


        </x-filament::input.wrapper>
        @endif

        <div @if ($pollingInterval=$this->getPollingInterval()) wire:poll.{{ $pollingInterval }}="updateChartData" @endif>
            <div wire:key="chart-{{ $this->dataChecksum }}"
                @if (FilamentView::hasSpaMode()) x-load="visible"
                @else x-load @endif
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                x-data="chart({ cachedData: @js($cachedData), options: @js($chartOptions), type: @js($chartType) })"
                class="{{ is_string($color) && $color !== 'gray' ? 'fi-color-' . $color : '' }}"
                x-on:refreshChart.window="updateChart()">

                <canvas
                    x-ref="canvas"
                    @if ($maxHeight=$this->getMaxHeight())
                    style="max-height: {{ $maxHeight }}"
                    @endif
                    ></canvas>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('chart', ({
            cachedData,
            options,
            type
        }) => ({
            chart: null,
            init() {
                this.$nextTick(() => {
                    if (!window.Chart) {
                        console.error('Chart.js is not loaded');
                        return;
                    }
                    this.chart = new Chart(this.$refs.canvas, {
                        type,
                        data: cachedData,
                        options
                    });
                });
                this.$wire.on('updateChartData', ({
                    data
                }) => {
                    this.chart.data = data;
                    this.chart.update();
                });
            }
        }));
    });
</script>