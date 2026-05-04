<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Company;
use App\Models\Municipality;
use Illuminate\Support\Str;
use App\Models\Uploads;

class UploadsFactory extends Factory
{
    protected $model = Uploads::class;
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'municipality_id' => Municipality::factory(),
            'reference' => strtoupper(Str::random(10)),
            'status' => $this->faker->randomElement(['Pending', 'Processing', 'Completed', 'Rejected']),
            'submitted_at' => $this->faker->dateTimeBetween('-90 days', 'now'),
            'original_file_path' => json_encode(['uploads/' . Str::random(40) . '.eml']),
            'original_file_names' => json_encode(['example.eml']), // Add this
            'workings_file_path' => null,
            'workings_file_name' => null, // Add this
            'systems_import_file_path' => null,
            'systems_import_file_name' => null, // Add this
            'extracted_dates' => json_encode([$this->faker->optional()->dateTimeBetween('-90 days', 'now')->format('Y-m-d H:i:s')]),
            'system_import_date' => $this->faker->optional()->dateTimeBetween('-90 days', 'now'),
        ];
    }
}
