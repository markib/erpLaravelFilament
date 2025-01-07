<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\PaymentMethod;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Banking\BankAccount;
use App\Models\Parties\Customer;
use App\Utilities\Currency\CurrencyConverter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
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
            'discount_method' => $this->faker->randomElement([
                DocumentDiscountMethod::PerLineItem->value,
                DocumentDiscountMethod::PerDocument->value,
            ]), // Use enum-backed values
            'discount_computation' => $this->faker->randomElement(['percentage', 'fixed']),
            'discount_rate' => $this->faker->numberBetween(0, 100),
            'terms' => $this->faker->sentence,
            'footer' => $this->faker->sentence,
            'item_type' => 'Offering',
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function withLineItems(int $count = 3): self
    {
        return $this->has(DocumentLineItem::factory()->forInvoice()->count($count), 'lineItems');
    }

    public function approved(): static
    {
        return $this->afterCreating(function (Invoice $invoice) {
            if (! $invoice->isDraft()) {
                return;
            }

            $this->recalculateTotals($invoice);

            $approvedAt = Carbon::parse($invoice->date)->addHours($this->faker->numberBetween(1, 24));

            $invoice->approveDraft($approvedAt);
        });
    }

    public function withPayments(?int $min = 1, ?int $max = null, InvoiceStatus $invoiceStatus = InvoiceStatus::Paid): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($invoiceStatus, $max, $min) {
            if ($invoice->isDraft()) {
                $this->recalculateTotals($invoice);

                $approvedAt = Carbon::parse($invoice->date)->addHours($this->faker->numberBetween(1, 24));
                $invoice->approveDraft($approvedAt);
            }

            $invoice->refresh();

            $totalAmountDue = $invoice->getRawOriginal('amount_due');

            if ($invoiceStatus === InvoiceStatus::Overpaid) {
                $totalAmountDue += random_int(1000, 10000);
            } elseif ($invoiceStatus === InvoiceStatus::Partial) {
                $totalAmountDue = (int) floor($totalAmountDue * 0.5);
            }

            if ($totalAmountDue <= 0 || empty($totalAmountDue)) {
                return;
            }

            $paymentCount = $max && $min ? $this->faker->numberBetween($min, $max) : $min;
            $paymentAmount = (int) floor($totalAmountDue / $paymentCount);
            $remainingAmount = $totalAmountDue;

            $paymentDate = Carbon::parse($invoice->approved_at);
            $paymentDates = [];

            for ($i = 0; $i < $paymentCount; $i++) {
                $amount = $i === $paymentCount - 1 ? $remainingAmount : $paymentAmount;

                if ($amount <= 0) {
                    break;
                }

                $postedAt = $paymentDate->copy()->addDays($this->faker->numberBetween(1, 30));
                $paymentDates[] = $postedAt;

                $data = [
                    'posted_at' => $postedAt,
                    'amount' => CurrencyConverter::convertCentsToFormatSimple($amount, $invoice->currency_code),
                    'payment_method' => $this->faker->randomElement(PaymentMethod::class),
                    'bank_account_id' => BankAccount::inRandomOrder()->value('id'),
                    'notes' => $this->faker->sentence,
                ];

                $invoice->recordPayment($data);
                $remainingAmount -= $amount;
            }

            // If it's a paid invoice, use the latest payment date as paid_at
            if ($invoiceStatus === InvoiceStatus::Paid) {
                $latestPaymentDate = max($paymentDates);
                $invoice->updateQuietly([
                    'status' => $invoiceStatus,
                    'paid_at' => $latestPaymentDate,
                ]);
            }
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice) {
            // Use the invoice's ID to generate invoice and order numbers
            $paddedId = str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT);

            $invoice->updateQuietly([
                'invoice_number' => "INV-{$paddedId}",
                'order_number' => "ORD-{$paddedId}",
            ]);

            $this->recalculateTotals($invoice);

            if ($invoice->approved_at && $invoice->is_currently_overdue) {
                $invoice->updateQuietly([
                    'status' => InvoiceStatus::Overdue,
                ]);
            }
        });
    }

    protected function recalculateTotals(Invoice $invoice): void
    {
        if ($invoice->lineItems()->exists()) {
            $invoice->refresh();
            $subtotal = $invoice->lineItems()->sum('subtotal') / 100;
            $taxTotal = $invoice->lineItems()->sum('tax_total') / 100;
            $discountTotal = $invoice->lineItems()->sum('discount_total') / 100;
            $grandTotal = $subtotal + $taxTotal - $discountTotal;

            $invoice->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'total' => $grandTotal,
            ]);
        }
    }
}
