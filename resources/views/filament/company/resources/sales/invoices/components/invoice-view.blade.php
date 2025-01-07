@php
/** @var \App\Models\Accounting\Invoice $invoice */
$invoiceSettings = $invoice->company->defaultInvoice;

$company = $invoice->company;

use App\Utilities\Currency\CurrencyConverter;
@endphp

<x-company.invoice.container class="modern-template-container">
    <!-- Colored Header with Logo -->
    <x-company.invoice.header class="bg-gray-800 h-24">
        <!-- Logo -->
        <div class="w-2/3">
            @if($invoice->logo && $invoiceSettings->show_logo)
            <x-company.invoice.logo class="ml-8" :src="$invoice->logo" />
            @endif
        </div>

        <!-- Ribbon Container -->
        <div class="w-1/3 absolute right-0 top-0 p-3 h-32 flex flex-col justify-end rounded-bl-sm"
            style="background: {{ $invoiceSettings->accent_color }};">
            @if($invoice->header)
            <h1 class="text-4xl font-bold text-white text-center uppercase">{{ $invoice->header }}</h1>
            @endif
        </div>
    </x-company.invoice.header>

    <!-- Company Details -->
    <x-company.invoice.metadata class="modern-template-metadata space-y-8">
        <div class="text-sm">
            <h2 class="text-lg font-semibold">{{ $company->name }}</h2>
            @if($company->profile->address && $company->profile->city?->name && $company->profile->state?->name && $company->profile?->zip_code)
            <p>{{ $company->profile->address }}</p>
            <p>{{ $company->profile->city->name }}
                , {{ $company->profile->state->name }} {{ $company->profile->zip_code }}</p>
            <p>{{ $company->profile->state->country->name }}</p>
            @endif
        </div>

        <div class="flex justify-between items-end">
            <!-- Billing Details -->
            <div class="text-sm tracking-tight">
                <h3 class="text-gray-600 dark:text-gray-400 font-medium tracking-tight mb-1">BILL TO</h3>
                <p class="text-base font-bold"
                    style="color: {{ $invoiceSettings->accent_color }}">{{ $invoice->client->name }}</p>

                @if($invoice->client->billingAddress)
                @php
                $address = $invoice->client->billingAddress;
                @endphp
                @if($address->address_line_1)
                <p>{{ $address->address_line_1 }}</p>
                @endif
                @if($address->address_line_2)
                <p>{{ $address->address_line_2 }}</p>
                @endif
                <p>
                    {{ $address->city }}{{ $address->state ? ', ' . $address->state : '' }}
                    {{ $address->postal_code }}
                </p>
                @if($address->country)
                <p>{{ $address->country }}</p>
                @endif
                @endif
            </div>

            <div class="text-sm tracking-tight">
                <table class="min-w-full">
                    <tbody>
                        <tr>
                            <td class="font-semibold text-right pr-2">Invoice Number:</td>
                            <td class="text-left pl-2">{{ $invoice->invoice_number }}</td>
                        </tr>
                        @if($invoice->order_number)
                        <tr>
                            <td class="font-semibold text-right pr-2">P.O/S.O Number:</td>
                            <td class="text-left pl-2">{{ $invoice->order_number }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="font-semibold text-right pr-2">Invoice Date:</td>
                            <td class="text-left pl-2">{{ $invoice->date->toDefaultDateFormat() }}</td>
                        </tr>
                        <tr>
                            <td class="font-semibold text-right pr-2">Payment Due:</td>
                            <td class="text-left pl-2">{{ $invoice->due_date->toDefaultDateFormat() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </x-company.invoice.metadata>

    <!-- Line Items Table -->
    <x-company.invoice.line-items class="modern-template-line-items">
        <table class="w-full text-left table-fixed">
            <thead class="text-sm leading-relaxed">
                <tr class="text-gray-600 dark:text-gray-400">
                    <th class="text-left pl-6 w-[45%] py-4">Items</th>
                    <th class="text-center w-[15%] py-4">Quantity</th>
                    <th class="text-right w-[20%] py-4">Price</th>
                    <th class="text-right pr-6 w-[20%] py-4">Amount</th>
                </tr>
            </thead>
            <tbody class="text-sm tracking-tight border-y-2">
                @foreach($invoice->lineItems as $index => $item)
                <tr @class(['bg-gray-100 dark:bg-gray-800'=> $index % 2 === 0])>
                    <td class="text-left pl-6 font-semibold py-3">
                        {{ $item->offering->name ?? $item->product->product_name }}
                        @if($item->description)
                        <div class="text-gray-600 font-normal line-clamp-2 mt-1">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="text-center py-3">{{ $item->quantity }}</td>
                    <td class="text-right py-3">{{ CurrencyConverter::formatToMoney($item->unit_price, $invoice->currency_code) }}</td>
                    <td class="text-right pr-6 py-3">{{ CurrencyConverter::formatToMoney($item->subtotal, $invoice->currency_code) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="text-sm tracking-tight">
                <tr>
                    <td class="pl-6 py-2" colspan="2"></td>
                    <td class="text-right font-semibold py-2">Subtotal:</td>
                    <td class="text-right pr-6 py-2">{{ CurrencyConverter::formatToMoney($invoice->subtotal, $invoice->currency_code) }}</td>
                </tr>
                @if($invoice->discount_total)
                <tr class="text-success-800 dark:text-success-600">
                    <td class="pl-6 py-2" colspan="2"></td>
                    <td class="text-right py-2">Discount:</td>
                    <td class="text-right pr-6 py-2">
                        ({{ CurrencyConverter::formatToMoney($invoice->discount_total, $invoice->currency_code) }})
                    </td>
                </tr>
                @endif
                @if($invoice->tax_total)
                <tr>
                    <td class="pl-6 py-2" colspan="2"></td>
                    <td class="text-right py-2">Tax:</td>
                    <td class="text-right pr-6 py-2">{{ CurrencyConverter::formatToMoney($invoice->tax_total, $invoice->currency_code) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="pl-6 py-2" colspan="2"></td>
                    <td class="text-right font-semibold border-t py-2">Total:</td>
                    <td class="text-right border-t pr-6 py-2">{{ CurrencyConverter::formatToMoney($invoice->total, $invoice->currency_code) }}</td>
                </tr>
                <tr>
                    <td class="pl-6 py-2" colspan="2"></td>
                    <td class="text-right font-semibold border-t-4 border-double py-2">Amount Due
                        ({{ $invoice->currency_code }}):
                    </td>
                    <td class="text-right border-t-4 border-double pr-6 py-2">{{ CurrencyConverter::formatToMoney($invoice->amount_due, $invoice->currency_code) }}</td>
                </tr>
            </tfoot>
        </table>
    </x-company.invoice.line-items>

    <!-- Footer Notes -->
    <x-company.invoice.footer class="modern-template-footer tracking-tight">
        <h4 class="font-semibold px-6 text-sm" style="color: {{ $invoiceSettings->accent_color }}">Terms &
            Conditions</h4>
        <span class="border-t-2 my-2 border-gray-300 block w-full"></span>
        <div class="flex justify-between space-x-4 px-6 text-sm">
            <p class="w-1/2 break-words line-clamp-4">{{ $invoice->terms }}</p>
            <p class="w-1/2 break-words line-clamp-4">{{ $invoice->footer }}</p>
        </div>
    </x-company.invoice.footer>
</x-company.invoice.container>