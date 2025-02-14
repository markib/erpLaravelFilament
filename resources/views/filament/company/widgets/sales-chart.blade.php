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
        <form wire:submit.prevent="applyDateFilter" class="mt-4">
            {{ $this->form}}
        </form>
        <div class="mt-2">
            <button wire:click="applyDateFilter"
                class="fi-button fi-button-primary px-4 py-2 text-white bg-primary-600 hover:bg-primary-700 rounded-lg">
                Filter
            </button>
        </div>

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