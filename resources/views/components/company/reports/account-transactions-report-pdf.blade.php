<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->getTitle() }}</title>
    <style>
        .category-header-row > td {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .category-summary-row > td,
        .table-footer-row > td {
            background-color: #ffffff;
            font-weight: bold;
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
            width: 100%;
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
    <div class="date-range">Date Range: {{ $startDate }} to {{ $endDate }}</div>
</div>
<table class="table-class">
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
            <td colspan="{{ count($report->getHeaders()) }}">
                <div>
                    @foreach($category->header as $headerRow)
                        <div>
                            @foreach($headerRow as $headerValue)
                                @if (!empty($headerValue))
                                    {{ $headerValue }}
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </td>
        </tr>
        @foreach($category->data as $dataIndex => $transaction)
            <tr
                @class([
                    'category-header-row' => $loop->first || $loop->last || $loop->remaining === 1,
                ])>
                @foreach($transaction as $cellIndex => $cell)
                    <td @class([
                            $report->getAlignmentClass($cellIndex),
                            'whitespace-normal' => $cellIndex === 'description',
                            'whitespace-nowrap' => $cellIndex !== 'description',
                        ])
                    >
                        @if(is_array($cell) && isset($cell['description']))
                            {{ $cell['description'] }}
                        @else
                            {{ $cell }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        @unless($loop->last)
            <tr class="spacer-row">
                <td colspan="{{ count($report->getHeaders()) }}"></td>
            </tr>
        @endunless
        </tbody>
    @endforeach
</table>
</body>
</html>
