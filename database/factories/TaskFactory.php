<?php

namespace Database\Factories;

use App\Models\OrgInstance;
use App\Models\Service;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => OrgInstance::factory(),
            'service_id' => Service::factory(),
            'poj_title' => fake()->sentence(4),
            'poj_description' => fake()->paragraph(),
            'status' => 'TODO',
            'reference_code' => 'CFG-2026-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
        ];
    }

    public function done(): static
    {
        return $this->state(['status' => 'DONE']);
    }
}
