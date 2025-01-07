<?php

namespace App\View\Models;

use App\Enums\Accounting\DocumentType;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Common\Client;
use App\Models\Company;
use App\Models\Setting\DocumentDefault;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Illuminate\Database\Eloquent\Model;

class DocumentPreviewViewModel
{
    public function __construct(
        public Model $document,
        public DocumentType $documentType = DocumentType::Invoice,
    ) {}

    public function buildViewData(): array
    {
        return [
            'company' => $this->getCompanyDetails(),
            'client' => $this->getClientDetails(),
            'metadata' => $this->getDocumentMetadata(),
            'lineItems' => $this->getLineItems(),
            'totals' => $this->getTotals(),
            'header' => $this->document->header,
            'footer' => $this->document->footer,
            'terms' => $this->document->terms,
            'logo' => $this->document->logo,
            'style' => $this->getStyle(),
            'labels' => $this->documentType->getLabels(),
        ];
    }

    private function getCompanyDetails(): array
    {
        /** @var Company $company */
        $company = $this->document->company;
        $profile = $company->profile;

        return [
            'name' => $company->name,
            'address' => $profile->address ?? '',
            'city' => $profile->city?->name ?? '',
            'state' => $profile->state?->name ?? '',
            'zip_code' => $profile->zip_code ?? '',
            'country' => $profile->state?->country->name ?? '',
        ];
    }

    private function getClientDetails(): array
    {
        /** @var Client $client */
        $client = $this->document->client;
        $address = $client->billingAddress ?? null;

        return [
            'name' => $client->name,
            'address_line_1' => $address->address_line_1 ?? '',
            'address_line_2' => $address->address_line_2 ?? '',
            'city' => $address->city ?? '',
            'state' => $address->state ?? '',
            'postal_code' => $address->postal_code ?? '',
            'country' => $address->country ?? '',
        ];
    }

    private function getDocumentMetadata(): array
    {
        return [
            'number' => $this->document->invoice_number ?? $this->document->estimate_number,
            'reference_number' => $this->document->order_number ?? $this->document->reference_number,
            'date' => $this->document->date?->toDefaultDateFormat(),
            'due_date' => $this->document->due_date?->toDefaultDateFormat() ?? $this->document->expiration_date?->toDefaultDateFormat(),
            'currency_code' => $this->document->currency_code ?? CurrencyAccessor::getDefaultCurrency(),
        ];
    }

    private function getLineItems(): array
    {
        $currencyCode = $this->document->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        return $this->document->lineItems->map(fn (DocumentLineItem $item) => [
            'name' => $item->offering->name ?? '',
            'description' => $item->description ?? '',
            'quantity' => $item->quantity,
            'unit_price' => CurrencyConverter::formatToMoney($item->unit_price, $currencyCode),
            'subtotal' => CurrencyConverter::formatToMoney($item->subtotal, $currencyCode),
        ])->toArray();
    }

    private function getTotals(): array
    {
        $currencyCode = $this->document->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        return [
            'subtotal' => CurrencyConverter::formatToMoney($this->document->subtotal, $currencyCode),
            'discount' => CurrencyConverter::formatToMoney($this->document->discount_total, $currencyCode),
            'tax' => CurrencyConverter::formatToMoney($this->document->tax_total, $currencyCode),
            'total' => CurrencyConverter::formatToMoney($this->document->total, $currencyCode),
            'amount_due' => $this->document->amount_due ? CurrencyConverter::formatToMoney($this->document->amount_due, $currencyCode) : null,
        ];
    }

    private function getStyle(): array
    {
        /** @var DocumentDefault $settings */
        $settings = $this->document->company->defaultInvoice;

        return [
            'accent_color' => $settings->accent_color ?? '#000000',
            'show_logo' => $settings->show_logo ?? false,
        ];
    }
}
