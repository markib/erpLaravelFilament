@php
use App\View\Models\DocumentPreviewViewModel;
use App\Enums\Accounting\DocumentType;

$type = $getType();
$viewModel = new DocumentPreviewViewModel($getRecord(), $type);
extract($viewModel->buildViewData(), EXTR_SKIP);
@endphp

<div {{ $attributes }}>
    <x-company.invoice.container class="modern-template-container">
        <!-- Colored Header with Logo -->
        <x-company.invoice.header class="bg-gray-800 h-24">
            <!-- Logo -->
            <div class="w-2/3">
                @if($logo && $style['show_logo'])
                <x-company.invoice.logo class="ml-8" :src="$logo" />
                @endif
            </div>

            <!-- Ribbon Container -->
            <div class="w-1/3 absolute right-0 top-0 p-3 h-32 flex flex-col justify-end rounded-bl-sm"
                style="background: {{ $style['accent_color'] }};">
                @if($header)
                <h1 class="text-4xl font-bold text-white text-center uppercase">{{ $header }}</h1>
                @endif
            </div>
        </x-company.invoice.header>

        <!-- Company Details -->
        <x-company.invoice.metadata class="modern-template-metadata space-y-8">
            <div class="text-sm">
                <h2 class="text-lg font-semibold">{{ $company['name'] }}</h2>
                @if($company['address'] && $company['city'] && $company['state'] && $company['zip_code'])
                <p>{{ $company['address'] }}</p>
                <p>{{ $company['city'] }}
                    , {{ $company['state'] }} {{ $company['zip_code'] }}</p>
                <p>{{ $company['country'] }}</p>
                @endif
            </div>

            <div class="flex justify-between items-end">
                <!-- Billing Details -->
                <div class="text-sm tracking-tight">
                    <h3 class="text-gray-600 dark:text-gray-400 font-medium tracking-tight mb-1">BILL TO</h3>
                    <p class="text-base font-bold"
                        style="color: {{ $style['accent_color'] }}">{{ $client['name'] }}</p>

                    @if($client['address_line_1'])
                    <p>{{ $client['address_line_1'] }}</p>

                    @if($client['address_line_2'])
                    <p>{{ $client['address_line_2'] }}</p>
                    @endif
                    <p>
                        {{ $client['city'] }}{{ $client['state'] ? ', ' . $client['state'] : '' }}
                        {{ $client['postal_code'] }}
                    </p>
                    @if($client['country'])
                    <p>{{ $client['country'] }}</p>
                    @endif
                    @endif
                </div>

                <div class="text-sm tracking-tight">
                    <table class="min-w-full">
                        <tbody>
                            <tr>
                                <td class="font-semibold text-right pr-2">{{ $labels['number'] }}:</td>
                                <td class="text-left pl-2">{{ $metadata['number'] }}</td>
                            </tr>
                            @if($metadata['reference_number'])
                            <tr>
                                <td class="font-semibold text-right pr-2">{{ $labels['reference_number'] }}:</td>
                                <td class="text-left pl-2">{{ $metadata['reference_number'] }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="font-semibold text-right pr-2">{{ $labels['date'] }}:</td>
                                <td class="text-left pl-2">{{ $metadata['date'] }}</td>
                            </tr>
                            <tr>
                                <td class="font-semibold text-right pr-2">{{ $labels['due_date'] }}:</td>
                                <td class="text-left pl-2">{{ $metadata['due_date'] }}</td>
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
                    @foreach($lineItems as $index => $item)
                    <tr @class(['bg-gray-100 dark:bg-gray-800'=> $index % 2 === 0])>
                        <td class="text-left pl-6 font-semibold py-3">
                            {{ $item['name'] }}
                            @if($item['description'])
                            <div class="text-gray-600 font-normal line-clamp-2 mt-1">{{ $item['description'] }}</div>
                            @endif
                        </td>
                        <td class="text-center py-3">{{ $item['quantity'] }}</td>
                        <td class="text-right py-3">{{ $item['unit_price'] }}</td>
                        <td class="text-right pr-6 py-3">{{ $item['subtotal'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="text-sm tracking-tight">
                    <tr>
                        <td class="pl-6 py-2" colspan="2"></td>
                        <td class="text-right font-semibold py-2">Subtotal:</td>
                        <td class="text-right pr-6 py-2">{{ $totals['subtotal'] }}</td>
                    </tr>
                    @if($totals['discount'])
                    <tr class="text-success-800 dark:text-success-600">
                        <td class="pl-6 py-2" colspan="2"></td>
                        <td class="text-right py-2">Discount:</td>
                        <td class="text-right pr-6 py-2">
                            ({{ $totals['discount'] }})
                        </td>
                    </tr>
                    @endif
                    @if($totals['tax'])
                    <tr>
                        <td class="pl-6 py-2" colspan="2"></td>
                        <td class="text-right py-2">Tax:</td>
                        <td class="text-right pr-6 py-2">{{ $totals['tax'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="pl-6 py-2" colspan="2"></td>
                        <td class="text-right font-semibold border-t py-2">Total:</td>
                        <td class="text-right border-t pr-6 py-2">{{ $totals['total'] }}</td>
                    </tr>
                    @if($totals['amount_due'])
                    <tr>
                        <td class="pl-6 py-2" colspan="2"></td>
                        <td class="text-right font-semibold border-t-4 border-double py-2">Amount Due
                            ({{ $metadata['currency_code'] }}):
                        </td>
                        <td class="text-right border-t-4 border-double pr-6 py-2">{{ $totals['amount_due'] }}</td>
                    </tr>
                    @else
                    <tr>
                        <td class="pl-6 py-2" colspan="2"></td>
                        <td class="text-right font-semibold border-t-4 border-double py-2">Grand Total
                            ({{ $metadata['currency_code'] }}):
                        </td>
                        <td class="text-right border-t-4 border-double pr-6 py-2">{{ $totals['total'] }}</td>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </x-company.invoice.line-items>

        <!-- Footer Notes -->
        <x-company.invoice.footer class="modern-template-footer tracking-tight">
            <h4 class="font-semibold px-6 text-sm" style="color: {{ $style['accent_color'] }}">
                Terms & Conditions
            </h4>
            <span class="border-t-2 my-2 border-gray-300 block w-full"></span>
            <div class="flex justify-between space-x-4 px-6 text-sm">
                <p class="w-1/2 break-words line-clamp-4">{{ $terms }}</p>
                <p class="w-1/2 break-words line-clamp-4">{{ $footer }}</p>
            </div>
        </x-company.invoice.footer>
    </x-company.invoice.container>
</div>