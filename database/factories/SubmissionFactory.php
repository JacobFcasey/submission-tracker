<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Company;
use App\Models\Municipality;

class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'municipality_id' => Municipality::factory(),
            'reference' => strtoupper($this->faker->bothify('SUB-####??')),
            'amount' => $this->faker->randomFloat(2, 1000, 250000),
            'status' => $this->faker->randomElement(['pending','approved','reconciled','rejected']),
            'submitted_at' => $this->faker->dateTimeBetween('-90 days', 'now'),
        ];
    }
}
