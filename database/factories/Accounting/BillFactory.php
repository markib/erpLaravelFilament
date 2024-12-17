<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\PaymentMethod;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Banking\BankAccount;
use App\Models\Common\Vendor;
use App\Models\Parties\Supplier;
use App\Utilities\Currency\CurrencyConverter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Bill::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 50% chance of being a future bill
        $isFutureBill = $this->faker->boolean();

        if ($isFutureBill) {
            // For future bills, date is recent and due date is in near future
            $billDate = $this->faker->dateTimeBetween('-10 days', '+10 days');
        } else {
            // For past bills, both date and due date are in the past
            $billDate = $this->faker->dateTimeBetween('-1 year', '-30 days');
        }

        $dueDays = $this->faker->numberBetween(14, 60);

        return [
            'company_id' => 1,
            'vendor_id' => Supplier::inRandomOrder()->value('id'),
            'bill_number' => $this->faker->unique()->numerify('BILL-#####'),
            'order_number' => $this->faker->unique()->numerify('PO-#####'),
            'date' => $billDate,
            'due_date' => Carbon::parse($billDate)->addDays($dueDays),
            'status' => BillStatus::Unpaid,
            'currency_code' => 'USD',
            'notes' => $this->faker->sentence,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function withLineItems(int $count = 3): self
    {
        return $this->has(DocumentLineItem::factory()->forBill()->count($count), 'lineItems');
    }

    public function initialized(): static
    {
        return $this->afterCreating(function (Bill $bill) {
            if ($bill->hasInitialTransaction()) {
                return;
            }

            $this->recalculateTotals($bill);

            $postedAt = Carbon::parse($bill->date)->addHours($this->faker->numberBetween(1, 24));

            $bill->createInitialTransaction($postedAt);
        });
    }

    public function withPayments(?int $min = 1, ?int $max = null, BillStatus $billStatus = BillStatus::Paid): static
    {
        return $this->afterCreating(function (Bill $bill) use ($billStatus, $max, $min) {
            if (! $bill->hasInitialTransaction()) {
                $this->recalculateTotals($bill);

                $postedAt = Carbon::parse($bill->date)->addHours($this->faker->numberBetween(1, 24));

                $bill->createInitialTransaction($postedAt);
            }

            $bill->refresh();

            $totalAmountDue = $bill->getRawOriginal('amount_due');

            if ($billStatus === BillStatus::Partial) {
                $totalAmountDue = (int) floor($totalAmountDue * 0.5);
            }

            if ($totalAmountDue <= 0 || empty($totalAmountDue)) {
                return;
            }

            $paymentCount = $max && $min ? $this->faker->numberBetween($min, $max) : $min;
            $paymentAmount = (int) floor($totalAmountDue / $paymentCount);
            $remainingAmount = $totalAmountDue;

            $paymentDate = Carbon::parse($bill->initialTransaction->posted_at);
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
                    'amount' => CurrencyConverter::convertCentsToFormatSimple($amount, $bill->currency_code),
                    'payment_method' => $this->faker->randomElement(PaymentMethod::class),
                    'bank_account_id' => BankAccount::inRandomOrder()->value('id'),
                    'notes' => $this->faker->sentence,
                ];

                $bill->recordPayment($data);
                $remainingAmount -= $amount;
            }

            if ($billStatus === BillStatus::Paid) {
                $latestPaymentDate = max($paymentDates);
                $bill->updateQuietly([
                    'status' => $billStatus,
                    'paid_at' => $latestPaymentDate,
                ]);
            }
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Bill $bill) {
            $paddedId = str_pad((string) $bill->id, 5, '0', STR_PAD_LEFT);

            $bill->updateQuietly([
                'bill_number' => "BILL-{$paddedId}",
                'order_number' => "PO-{$paddedId}",
            ]);

            $this->recalculateTotals($bill);

            // Check for overdue status
            if ($bill->due_date < today() && $bill->status !== BillStatus::Paid) {
                $bill->updateQuietly([
                    'status' => BillStatus::Overdue,
                ]);
            }
        });
    }

    protected function recalculateTotals(Bill $bill): void
    {
        if ($bill->lineItems()->exists()) {
            $bill->refresh();
            $subtotal = $bill->lineItems()->sum('subtotal') / 100;
            $taxTotal = $bill->lineItems()->sum('tax_total') / 100;
            $discountTotal = $bill->lineItems()->sum('discount_total') / 100;
            $grandTotal = $subtotal + $taxTotal - $discountTotal;

            $bill->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'total' => $grandTotal,
            ]);
        }
    }
}
