<?php

namespace Database\Factories\Setting;

use App\Enums\Setting\DocumentType;
use App\Enums\Setting\Font;
use App\Enums\Setting\PaymentTerms;
use App\Enums\Setting\Template;
use App\Models\Setting\DocumentDefault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentDefault>
 */
class DocumentDefaultFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DocumentDefault::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoiceDate = $this->faker->dateTimeBetween('-1 year');

        return [
            'company_id' => 1, // Replace with actual company ID or use a factory
            //'type' => $this->faker->randomElement(['invoice', 'quote', 'order', 'bill']),
            'logo' => $this->faker->optional()->imageUrl(200, 200, 'business', true, 'logo'),
            'show_logo' => $this->faker->boolean,
            'number_prefix' => $this->faker->optional()->lexify('DOC-'),
            'number_digits' => 5,
            'number_next' => $this->faker->numberBetween(1, 1000),
            'payment_terms' => PaymentTerms::DEFAULT,
            'header' => $this->faker->optional()->sentence,
            'subheader' => $this->faker->optional()->sentence,
            'terms' => $this->faker->optional()->paragraph,
            'footer' => $this->faker->optional()->paragraph,
            'accent_color' => '#4F46E5',
            'font' => Font::DEFAULT,
            'template' => Template::DEFAULT,
            'item_name' => $this->faker->optional()->words(3, true),
            'unit_name' => $this->faker->optional()->words(3, true),
            'price_name' => $this->faker->optional()->words(3, true),
            'amount_name' => $this->faker->optional()->words(3, true),
            'created_by' => $this->faker->optional()->numberBetween(1, 10),
            'updated_by' => $this->faker->optional()->numberBetween(1, 10),
        ];
    }

    /**
     * The model's common default state.
     */
    private function baseState(DocumentType $type, string $prefix, string $header): array
    {
        return [
            'type' => $type->value,
            'number_prefix' => $prefix,
            'header' => $header,
            'item_name' => ['option' => 'items', 'custom' => null],
            'unit_name' => ['option' => 'quantity', 'custom' => null],
            'price_name' => ['option' => 'price', 'custom' => null],
            'amount_name' => ['option' => 'amount', 'custom' => null],
        ];
    }

    /**
     * Indicate that the model's type is invoice.
     */
    public function invoice(): self
    {
        return $this->state($this->baseState(DocumentType::Invoice, 'INV-', 'Invoice'));
    }

    /**
     * Indicate that the model's type is bill.
     */
    public function bill(): self
    {
        return $this->state($this->baseState(DocumentType::Bill, 'BILL-', 'Bill'));
    }
}
