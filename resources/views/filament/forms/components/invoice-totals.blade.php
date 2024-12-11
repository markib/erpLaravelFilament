@use('App\Utilities\Currency\CurrencyAccessor')

@php
$data = $this->form->getRawState();
$viewModel = new \App\View\Models\InvoiceTotalViewModel($this->record, $data);
extract($viewModel->buildViewData(), \EXTR_SKIP);
@endphp

<div class="totals-summary w-full pr-14">
    <table class="w-full text-right table-fixed">
        <tbody>
            <tr>
                <td class="w-2/3 text-sm px-4 py-2 font-medium leading-6 text-gray-950 dark:text-white">Subtotal:</td>
                <td class="w-1/3 text-sm pl-4 py-2 leading-6">{{ $subtotal }}</td>
            </tr>
            <tr>
                <td class="w-2/3 text-sm px-4 py-2 font-medium leading-6 text-gray-950 dark:text-white">Taxes:</td>
                <td class="w-1/3 text-sm pl-4 py-2 leading-6">{{ $taxTotal }}</td>
            </tr>
            <tr>
                <td class="w-2/3 text-sm px-4 py-2 font-medium leading-6 text-gray-950 dark:text-white">Discounts:</td>
                <td class="w-1/3 text-sm pl-4 py-2 leading-6">({{ $discountTotal }})</td>
            </tr>
            <tr class="font-semibold">
                <td class="w-2/3 text-sm px-4 py-2 font-medium leading-6 text-gray-950 dark:text-white">Total:</td>
                <td class="w-1/3 text-sm pl-4 py-2 leading-6">{{ $grandTotal }}</td>
            </tr>
        </tbody>
    </table>
</div>