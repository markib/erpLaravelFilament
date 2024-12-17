<?php

namespace App\Services;

use App\Contracts\ExportableReport;
use App\Models\Company;
use App\Transformers\CashFlowStatementReportTransformer;
use Mpdf\Mpdf;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use League\Csv\Bom;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function exportToCsv(Company $company, ExportableReport $report, ?string $startDate = null, ?string $endDate = null): StreamedResponse
    {
        if ($startDate && $endDate) {
            $formattedStartDate = Carbon::parse($startDate)->toDateString();
            $formattedEndDate = Carbon::parse($endDate)->toDateString();
            $dateLabel = $formattedStartDate . ' to ' . $formattedEndDate;
        } else {
            $formattedAsOfDate = Carbon::parse($endDate)->toDateString();
            $dateLabel = $formattedAsOfDate;
        }

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        $filename = $company->name . ' ' . $report->getTitle() . ' ' . $dateLabel . ' ' . $timestamp . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($startDate, $endDate, $report, $company) {
            $csv = Writer::createFromStream(fopen('php://output', 'wb'));
            $csv->setOutputBOM(Bom::Utf8);

            if ($startDate && $endDate) {
                $defaultStartDateFormat = Carbon::parse($startDate)->toDefaultDateFormat();
                $defaultEndDateFormat = Carbon::parse($endDate)->toDefaultDateFormat();
                $dateLabel = 'Date Range: ' . $defaultStartDateFormat . ' to ' . $defaultEndDateFormat;
            } else {
                $dateLabel = 'As of ' . Carbon::parse($endDate)->toDefaultDateFormat();
            }

            $csv->insertOne([$report->getTitle()]);
            $csv->insertOne([$company->name]);
            $csv->insertOne([$dateLabel]);
            $csv->insertOne([]);

            $csv->insertOne($report->getHeaders());

            foreach ($report->getCategories() as $category) {
                $this->writeDataRowsToCsv($csv, $category->header, $category->data, $report->getColumns());

                foreach ($category->types ?? [] as $type) {
                    $this->writeDataRowsToCsv($csv, $type->header, $type->data, $report->getColumns());

                    if (filled($type->summary)) {
                        $csv->insertOne($type->summary);
                    }
                }

                if (filled($category->summary)) {
                    $csv->insertOne($category->summary);
                }

                $csv->insertOne([]);
            }

            if ($report->getTitle() === 'Cash Flow Statement') {
                $this->writeOverviewTableToCsv($csv, $report);
            }

            if (filled($report->getOverallTotals())) {
                $csv->insertOne($report->getOverallTotals());
            }
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    /**
     * @throws CannotInsertRecord
     * @throws Exception
     */
    protected function writeOverviewTableToCsv(Writer $csv, ExportableReport $report): void
    {
        /** @var CashFlowStatementReportTransformer $report */
        $headers = $report->getOverviewHeaders();

        if (filled($headers)) {
            $csv->insertOne($headers);
        }

        foreach ($report->getOverview() as $overviewCategory) {
            if (filled($overviewCategory->header)) {
                $this->writeDataRowsToCsv($csv, $overviewCategory->header, $overviewCategory->data, $report->getColumns());
            }

            if (filled($overviewCategory->summary)) {
                $csv->insertOne($overviewCategory->summary);
            }

            if ($overviewCategory->header['account_name'] === 'Starting Balance') {
                foreach ($report->getOverviewAlignedWithColumns() as $summaryRow) {
                    $row = [];

                    foreach ($report->getColumns() as $column) {
                        $columnName = $column->getName();
                        $row[] = $summaryRow[$columnName] ?? '';
                    }

                    if (array_filter($row)) {
                        $csv->insertOne($row);
                    }
                }
            }
        }
    }

    public function exportToPdf(Company $company, ExportableReport $report, ?string $startDate = null, ?string $endDate = null): StreamedResponse
    {
        if ($startDate && $endDate) {
            $formattedStartDate = Carbon::parse($startDate)->toDateString();
            $formattedEndDate = Carbon::parse($endDate)->toDateString();
            $dateLabel = $formattedStartDate . ' to ' . $formattedEndDate;
        } else {
            $formattedAsOfDate = Carbon::parse($endDate)->toDateString();
            $dateLabel = $formattedAsOfDate;
        }

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        $filename = $company->name . ' ' . $report->getTitle() . ' ' . $dateLabel . ' ' . $timestamp . '.pdf';

        // Render the Blade view to HTML
        $htmlContent = view($report->getPdfView(), [
            'company' => $company,
            'report' => $report,
            'startDate' => $startDate ? Carbon::parse($startDate)->toDefaultDateFormat() : null,
            'endDate' => $endDate ? Carbon::parse($endDate)->toDefaultDateFormat() : null,
        ])->render();

        // Initialize mPDF
        $mpdf = new Mpdf();

        // Write the HTML content to the PDF
        $mpdf->WriteHTML($htmlContent);

        // Stream the PDF to the browser
        return response()->streamDownload(function () use ($mpdf) {
            // Output PDF directly to the browser
            echo $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        }, $filename);
    }

    /**
     * @throws CannotInsertRecord
     * @throws Exception
     */
    protected function writeDataRowsToCsv(Writer $csv, array $header, array $data, array $columns): void
    {
        if (isset($header[0]) && is_array($header[0])) {
            foreach ($header as $headerRow) {
                $csv->insertOne($headerRow);
            }
        } else {
            $csv->insertOne($header);
        }

        // Output data rows
        foreach ($data as $rowData) {
            $row = [];

            foreach ($columns as $column) {
                $columnName = $column->getName();
                $cell = $rowData[$columnName] ?? '';

                if ($column->isDate()) {
                    try {
                        $row[] = Carbon::parse($cell)->toDateString();
                    } catch (InvalidFormatException) {
                        $row[] = $cell;
                    }
                } elseif (is_array($cell)) {
                    $row[] = $cell['name'] ?? $cell['description'] ?? '';
                } else {
                    $row[] = $cell;
                }
            }

            $csv->insertOne($row);
        }
    }
}
