<?php

namespace Database\Factories\Setting;

use App\Enums\Setting\EntityType;
use App\Faker\State;
use App\Models\Company;
use App\Models\Setting\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CompanyProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $countryCode = $this->faker->countryCode;

        return [
            'address' => $this->faker->streetAddress,
            'zip_code' => $this->faker->postcode,
            'state_id' => $this->faker->state($countryCode),
            'country' => $countryCode,
            'phone_number' => $this->faker->phoneNumberForCountryCode($countryCode),
            'email' => $this->faker->email,
            'entity_type' => $this->faker->randomElement(EntityType::class),
        ];
    }

    public function withCountry(string $code): self
    {
        return $this->state([
            'country' => $code,
            'state_id' => $this->faker->state($code),
            'phone_number' => $this->faker->phoneNumberForCountryCode($code),
        ]);
    }

    public function forCompany(Company $company): self
    {
        return $this->state([
            'company_id' => $company->id,
            'created_by' => $company->owner->id,
            'updated_by' => $company->owner->id,
        ]);
    }
}
