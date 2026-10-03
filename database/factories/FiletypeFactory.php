<?php

namespace Database\Factories;

use App\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Filetype>
 */
class FiletypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => substr(fake()->unique()->words(2, true), 0, 30),
            'firm_id' => Firm::factory(),
            'set_as_default' => false,
        ];
    }

    public function asDefault(): static
    {
        return $this->state(fn (array $attributes) => ['set_as_default' => true]);
    }
}
