<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\OrderStatus;
use App\Enums\Common\ItemType;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Order;
use App\Models\Parties\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 50% chance of being a future bill
        $isFutureOrder = $this->faker->boolean();

        if ($isFutureOrder) {
            // For future orders, date is recent and due date is in near future
            $orderDate = $this->faker->dateTimeBetween('-10 days', '+10 days');
        } else {
            // For past orders, both date and due date are in the past
            $orderDate = $this->faker->dateTimeBetween('-1 year', '-30 days');
        }

        $dueDays = $this->faker->numberBetween(14, 60);
        // Ensure suppliers exist
        Supplier::factory()->count(5)->create();

        return [
            // 'company_id' => 1,
            'vendor_id' => Supplier::inRandomOrder()->value('id'),
            'order_number' => $this->faker->unique()->numerify('ORD-#####'),
            'date' => $orderDate,
            'expiration_date' => Carbon::parse($orderDate)->addDays($dueDays),
            'status' => OrderStatus::Draft,
            'currency_code' => 'USD',
            'terms' => $this->faker->sentence,
            'discount_method' => $this->faker->randomElement([
                DocumentDiscountMethod::PerLineItem->value,
                DocumentDiscountMethod::PerDocument->value,
            ]), // Use enum-backed values
            'discount_computation' => $this->faker->randomElement(['percentage', 'fixed']),
            'subtotal' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => 0,
            'footer' => $this->faker->sentence,
            'item_type' => $this->faker->randomElement([ItemType::offering->value, ItemType::inventory_product->value]),
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function withLineItems(int $count = 3): self
    {
        return $this->has(DocumentLineItem::factory()->forOrder()->count($count), 'lineItems');
    }

    public function initialized(): static
    {
        return $this->afterCreating(function (Order $order) {
            // if ($order->hasInitialTransaction()) {
            //     return;
            // }

            $this->recalculateTotals($order);

            //  $postedAt = Carbon::parse($order->date)->addHours($this->faker->numberBetween(1, 24));

            //$order->createInitialTransaction($postedAt);
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            $paddedId = str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);

            $order->updateQuietly([
                'order_number' => "ORD-{$paddedId}",
            ]);

            $this->recalculateTotals($order);

        });
    }

    protected function recalculateTotals(Order $order): void
    {
        if ($order->lineItems()->exists()) {
            $order->refresh();
            $subtotal = $order->lineItems()->sum('subtotal') / 100;
            $taxTotal = $order->lineItems()->sum('tax_total') / 100;
            $discountTotal = $order->lineItems()->sum('discount_total') / 100;
            $grandTotal = $subtotal + $taxTotal - $discountTotal;

            $order->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'total' => $grandTotal,
            ]);
        }
    }
}
