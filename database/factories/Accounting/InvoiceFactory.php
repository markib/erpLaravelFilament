<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use App\Filament\Company\Clusters\Settings\Pages\Invoice;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Parties\Customer;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoiceDate = $this->faker->dateTimeBetween('-1 year');

        return [
            'company_id' => 1,
            'client_id' => Customer::inRandomOrder()->value('id'),
            'header' => 'Invoice',
            'subheader' => 'Invoice',
            'invoice_number' => $this->faker->unique()->numerify('INV-#####'),
            'order_number' => $this->faker->unique()->numerify('ORD-#####'),
            'date' => $invoiceDate,
            'due_date' => Carbon::parse($invoiceDate)->addDays($this->faker->numberBetween(14, 60)),
            'status' => InvoiceStatus::Draft,
            'currency_code' => 'USD',
            'terms' => $this->faker->sentence,
            'footer' => $this->faker->sentence,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function withLineItems(int $count = 3): self
    {
        return $this->has(DocumentLineItem::factory()->forInvoice()->count($count), 'lineItems');
    }

}
