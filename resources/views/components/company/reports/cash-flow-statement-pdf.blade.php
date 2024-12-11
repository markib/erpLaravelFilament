<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->getTitle() }}</title>
    <style>
        .font-bold {
            font-weight: bold;
        }

        .category-header-row > td,
        .type-header-row > td {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .category-summary-row > td,
        .table-footer-row > td,
        .type-summary-row > td {
            background-color: #ffffff;
            font-weight: bold;
        }

        .cell {
            padding-bottom: 5px;
            position: relative;
        }

        .company-name {
            font-size: 1.125rem;
            font-weight: bold;
        }

        .date-range {
            font-size: 0.875rem;
        }

        .header {
            color: #374151;
            margin-bottom: 1rem;
        }

        .header div + div {
            margin-top: 0.5rem;
        }

        .spacer-row > td {
            height: 0.75rem;
        }

        .table-body tr {
            background-color: #ffffff;
        }

        .table-class {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .table-class .type-row-indent {
            padding-left: 1.5rem;
        }

        .table-class td,
        .table-class th {
            border-bottom: 1px solid #d1d5db;
            color: #374151;
            font-size: 0.75rem;
            line-height: 1rem;
            padding: 0.75rem;
        }

        .table-head {
            display: table-row-group;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .title {
            font-size: 1.5rem;
        }

        .table-class .underline-bold {
            border-bottom: 2px solid #374151;
        }

        .table-class .underline-thin {
            border-bottom: 1px solid #374151;
        }

        .whitespace-normal {
            white-space: normal;
        }

        .whitespace-nowrap {
            white-space: nowrap;
        }

        table tfoot {
            display: table-row-group;
        }
    </style>
</head>
<body>
<div class="header">
    <div class="title">{{ $report->getTitle() }}</div>
    <div class="company-name">{{ $company->name }}</div>
    @if($startDate && $endDate)
        <div class="date-range">Date Range: {{ $startDate }} to {{ $endDate }}</div>
    @else
        <div class="date-range">As of {{ $endDate }}</div>
    @endif
</div>
<table class="table-class">
    <colgroup>
        @if(array_key_exists('account_code', $report->getHeaders()))
            <col span="1" style="width: 20%;">
            <col span="1" style="width: 55%;">
            <col span="1" style="width: 25%;">
        @else
            <col span="1" style="width: 65%;">
            <col span="1" style="width: 35%;">
        @endif
    </colgroup>
    <thead class="table-head">
    <tr>
        @foreach($report->getHeaders() as $index => $header)
            <th class="{{ $report->getAlignmentClass($index) }}">
                {{ $header }}
            </th>
        @endforeach
    </tr>
    </thead>
    @foreach($report->getCategories() as $category)
        <tbody>
        <tr class="category-header-row">
            @foreach($category->header as $index => $header)
                <td class="{{ $report->getAlignmentClass($index) }}">
                    {{ $header }}
                </td>
            @endforeach
        </tr>
        @foreach($category->data as $account)
            <tr>
                @foreach($account as $index => $cell)
                    <td @class([
                            $report->getAlignmentClass($index),
                            'whitespace-normal' => $index === 'account_name',
                            'whitespace-nowrap' => $index !== 'account_name',
                        ])
                    >
                        @if(is_array($cell) && isset($cell['name']))
                            {{ $cell['name'] }}
                        @else
                            {{ $cell }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach

        <!-- Category Types -->
        @foreach($category->types ?? [] as $type)
            <!-- Type Header -->
            <tr class="type-header-row">
                @foreach($type->header as $index => $header)
                    <td @class([
                            $report->getAlignmentClass($index),
                            'type-row-indent' => $index === 'account_name',
                        ])
                    >
                        {{ $header }}
                    </td>
                @endforeach
            </tr>

            <!-- Type Data -->
            @foreach($type->data as $typeRow)
                <tr class="type-data-row">
                    @foreach($typeRow as $index => $cell)
                        <td @class([
                                $report->getAlignmentClass($index),
                                'whitespace-normal type-row-indent' => $index === 'account_name',
                                'whitespace-nowrap' => $index !== 'account_name',
                            ])
                        >
                            @if(is_array($cell) && isset($cell['name']))
                                {{ $cell['name'] }}
                            @else
                                {{ $cell }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach

            <!-- Type Summary -->
            <tr class="type-summary-row">
                @foreach($type->summary as $index => $cell)
                    <td @class([
                            $report->getAlignmentClass($index),
                            'type-row-indent' => $index === 'account_name',
                        ])
                    >
                        {{ $cell }}
                    </td>
                @endforeach
            </tr>
        @endforeach

        <tr class="category-summary-row">
            @foreach($category->summary as $index => $cell)
                <td @class([
                        'cell',
                        $report->getAlignmentClass($index),
                        'underline-bold' => $loop->last,
                    ])
                >
                    {{ $cell }}
                </td>
            @endforeach
        </tr>

        @unless($loop->last && empty($report->getOverallTotals()))
            <tr class="spacer-row">
                <td colspan="{{ count($report->getHeaders()) }}"></td>
            </tr>
        @endunless
        </tbody>
    @endforeach
    <tfoot>
    <tr class="table-footer-row">
        @foreach ($report->getOverallTotals() as $index => $total)
            <td class="{{ $report->getAlignmentClass($index) }}">
                {{ $total }}
            </td>
        @endforeach
    </tr>
    </tfoot>
</table>

<!-- Second Overview Table -->
<table class="table-class">
    <colgroup>
        @if(array_key_exists('account_code', $report->getHeaders()))
            <col span="1" style="width: 20%;">
            <col span="1" style="width: 55%;">
            <col span="1" style="width: 25%;">
        @else
            <col span="1" style="width: 65%;">
            <col span="1" style="width: 35%;">
        @endif
    </colgroup>
    <thead class="table-head">
    <tr>
        @foreach($report->getOverviewHeaders() as $index => $header)
            <th class="{{ $report->getAlignmentClass($index) }}">
                {{ $header }}
            </th>
        @endforeach
    </tr>
    </thead>
    <!-- Overview Content -->
    @foreach($report->getOverview() as $overviewCategory)
        <tbody>
        <tr class="category-header-row">
            @foreach($overviewCategory->header as $index => $header)
                <td class="{{ $report->getAlignmentClass($index) }}">
                    {{ $header }}
                </td>
            @endforeach
        </tr>
        @foreach($overviewCategory->data as $overviewAccount)
            <tr>
                @foreach($overviewAccount as $index => $cell)
                    <td @class([
                            $report->getAlignmentClass($index),
                            'whitespace-normal' => $index === 'account_name',
                            'whitespace-nowrap' => $index !== 'account_name',
                        ])
                    >
                        @if(is_array($cell) && isset($cell['name']))
                            {{ $cell['name'] }}
                        @else
                            {{ $cell }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        <!-- Summary Row -->
        <tr class="category-summary-row">
            @foreach($overviewCategory->summary as $index => $summaryCell)
                <td class="{{ $report->getAlignmentClass($index) }}">
                    {{ $summaryCell }}
                </td>
            @endforeach
        </tr>

        @if($overviewCategory->header['account_name'] === 'Starting Balance')
            @foreach($report->getOverviewAlignedWithColumns() as $summaryRow)
                <tr>
                    @foreach($summaryRow as $index => $summaryCell)
                        <td @class([
                                'cell',
                                $report->getAlignmentClass($index),
                                'font-bold' => $loop->parent->last,
                                'underline-thin' => $loop->parent->remaining === 1 && $index === 'net_movement',
                                'underline-bold' => $loop->parent->last && $index === 'net_movement',
                            ])
                        >
                            {{ $summaryCell }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        @endif
        </tbody>
    @endforeach
</table>
</body>
</html>
