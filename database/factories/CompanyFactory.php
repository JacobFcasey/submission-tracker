<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Company; // Make sure this points to your Company model

class CompanyFactory extends Factory
{
    // Specify the corresponding model
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'registration_number' => 'REG-' . $this->faker->numerify('######'),
            'contact_email' => $this->faker->companyEmail,
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
