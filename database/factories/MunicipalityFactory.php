<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MunicipalityFactory extends Factory
{
    // Specify the model if not already set
    protected $model = \App\Models\Municipality::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->city() . ' Municipality',
            'province' => $this->faker->randomElement([
                'Gauteng', 'Western Cape', 'KwaZulu-Natal', 'Eastern Cape',
                'Free State', 'North West', 'Mpumalanga', 'Northern Cape', 'Limpopo'
            ]),
            'code' => $this->faker->unique()->numerify('M###'), // Ensure unique codes
        ];
    }
}
