<?php

namespace Database\Factories;

use App\Models\OrgInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgInstance>
 */
class OrgInstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['CFG', 'COMITE']),
            'recurrence_type' => fake()->randomElement(['HEBDO', 'OCCASIONNEL']),
            'date_meeting' => fake()->dateTimeBetween('now', '+3 months'),
            'is_archived' => false
        ];
    }
    public function archived(): static
    {
        return $this->state(['is_archived' => true]);
    }

    public function cfg(): static
    {
        return $this->state(['type' => 'CFG']);
    }

    public function comite(): static
    {
        return $this->state(['type' => 'COMITE']);
    }

}
